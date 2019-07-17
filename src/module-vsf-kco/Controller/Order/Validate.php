<?php
namespace Kodbruket\VsfKco\Controller\Order;

use Kodbruket\VsfKco\Model\ExtensionConstants;
use Kodbruket\VsfKco\Model\Klarna\DataTransform\Request\Address;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\DataObject;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\QuoteManagement;
use Psr\Log\LoggerInterface;
use Klarna\Core\Api\OrderRepositoryInterface;
use Klarna\Core\Model\OrderFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;

/**
 * Class Validate
 * @package Kodbruket\VsfKco\Controller\Order
 */
class Validate extends Action implements \Magento\Framework\App\CsrfAwareActionInterface
{
    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var DataObject
     */
    private $klarnaRequestData;

    /**
     * @var Address
     */
    private $addressDataTransform;

    /**
     * @var QuoteManagement
     */
    private $quoteManagement;

    /**
     * @var OrderFactory
     */
    private $klarnaOrderFactory;

    /**
     * @var OrderRepositoryInterface
     */
    private $klarnaOrderRepository;

    /**
     * @var CustomerFactory
     */
    private $customerFactory;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * Validate constructor.
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param LoggerInterface $logger
     * @param CartRepositoryInterface $cartRepository
     * @param Address $addressDataTransform
     * @param OrderFactory $klarnaOrderFactory
     * @param CustomerFactory $customerFactory
     * @param OrderRepositoryInterface $klarnaOrderRepository
     * @param CustomerRepositoryInterface $customerRepository
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        LoggerInterface $logger,
        CartRepositoryInterface $cartRepository,
        Address $addressDataTransform,
        OrderFactory $klarnaOrderFactory,
        CustomerFactory $customerFactory,
        OrderRepositoryInterface $klarnaOrderRepository,
        CustomerRepositoryInterface $customerRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        parent::__construct(
            $context
        );
        $this->jsonFactory = $jsonFactory;
        $this->logger = $logger;
        $this->cartRepository = $cartRepository;
        $this->addressDataTransform = $addressDataTransform;
        $this->klarnaOrderFactory = $klarnaOrderFactory;
        $this->klarnaOrderRepository = $klarnaOrderRepository;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    /**
     * @return DataObject
     */
    private function getKlarnaRequestData()
    {
        if (null === $this->klarnaRequestData) {
            /** @var \Magento\Framework\App\Request\Http $request */
            $request = $this->getRequest();
            $this->klarnaRequestData = new DataObject(
                json_decode($request->getContent(), true)
            );
        }

        return $this->klarnaRequestData;
    }

    /**
     * @return int
     */
    private function getKlarnaOrderId()
    {
        return $this->getKlarnaRequestData()->getData(
            'order_id'
        );
    }

    /**
     * @return int
     */
    private function getQuoteId()
    {
        $mask =  $this->getKlarnaRequestData()->getData(
            'merchant_reference2'
        );

        /** @var $quoteIdMask QuoteIdMask */
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($mask, 'masked_id');
        return $quoteIdMask->getQuoteId();
    }

    /**
     * Execute action based on request and return result
     *
     * Note: Request will be added as operation argument in future
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        $this->logger->debug('Validate: start');
        if (!$this->getRequest()->isPost()) {
            $this->logger->debug('Validate: No post request');

            $resultPage = $this->jsonFactory->create();
            $resultPage->setHttpResponseCode(404);
            return $resultPage;
        }

        $klarnaOderId = $this->getKlarnaOrderId();
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->cartRepository->get($this->getQuoteId());
        if (!$quote->getId() || !$quote->hasItems() || $quote->getHasError()) {
            $this->logger->debug('Validate: invalid magento quote');
            return $this->setValidateFailedResponse($klarnaOderId);
        }

        try {
            $checkoutData = $this->getKlarnaRequestData();
            $this->logger->debug('Input request :' . print_r($checkoutData->toArray(), true));
            if (!$quote->isVirtual()) {
                $this->logger->debug('updating order addresses');
                $this->updateOrderAddresses($checkoutData, $quote);
                $shippingMethod = $checkoutData->getData('selected_shipping_option');
                $quote->getShippingAddress()->setShippingMethod($shippingMethod['id']);
            }
            $quote->setData(ExtensionConstants::FORCE_ORDER_PLACE, true);
            $quote->getShippingAddress()->setPaymentMethod(\Klarna\Kp\Model\Payment\Kp::METHOD_CODE);

            $quote->getShippingAddress()
                ->setCollectShippingRates(true)
                ->collectShippingRates();
            $payment = $quote->getPayment();
            $payment->importData(['method' => \Klarna\Kp\Model\Payment\Kp::METHOD_CODE]);
            $payment->setAdditionalInformation(ExtensionConstants::FORCE_ORDER_PLACE, true);
            $payment->setAdditionalInformation(ExtensionConstants::KLARNA_ORDER_ID, $klarnaOderId);

            $quote->reserveOrderId();
            $this->cartRepository->save($quote);;

            /** @var \Klarna\Core\Model\Order $klarnaOrder */
            $klarnaOrder = $this->klarnaOrderFactory->create();
            $klarnaOrder->setData([
                'klarna_order_id' => $klarnaOderId,
                'reservation_id'  => $klarnaOderId,
            ]);
            $this->klarnaOrderRepository->save($klarnaOrder);

            return $this->resultFactory->create(ResultFactory::TYPE_RAW)->setHttpResponseCode(200);
        } catch (\Exception $exception) {
            $this->logger->critical('validation save kco Order' . $exception->getMessage());
            $this->logger->critical('validation save kco Order' . $exception->getTraceAsString());
            return $this->resultFactory->create(ResultFactory::TYPE_RAW)->setHttpResponseCode(500);
        }
    }

    /**
     * @param DataObject $checkoutData
     * @param \Magento\Quote\Model\Quote|CartInterface $quote
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function updateOrderAddresses(DataObject $checkoutData, CartInterface $quote)
    {
        if (!$checkoutData->hasBillingAddress() && !$checkoutData->hasShippingAddress()) {
            return;
        }

        $sameAsOther = $checkoutData->getShippingAddress() == $checkoutData->getBillingAddress();
        $billingAddress = new DataObject($checkoutData->getBillingAddress());
        $billingAddress->setSameAsOther($sameAsOther);
        $shippingAddress = new DataObject($checkoutData->getShippingAddress());
        $shippingAddress->setSameAsOther($sameAsOther);

        if (!$quote->getCustomerId()) {
            $websiteId = $quote->getStore()->getWebsiteId();
            $customer = $this->customerFactory->create();
            $customer->setWebsiteId($websiteId);
            $customer->loadByEmail($billingAddress->getEmail());
            if(!$customer->getEntityId()){
                $customer->setWebsiteId($websiteId)
                    ->setStore($quote->getStore())
                    ->setFirstname($billingAddress->getGivenName())
                    ->setLastname($billingAddress->getFamilyName())
                    ->setEmail($billingAddress->getEmail())
                    ->setPassword($billingAddress->getEmail());
                $customer->save();
            }
            $customer= $this->customerRepository->getById($customer->getEntityId());
            $quote->assignCustomer($customer);
        }

        $quote->getBillingAddress()->addData(
            $this->addressDataTransform->prepareMagentoAddress($billingAddress)
        );

        /**
         * @todo  check use 'Billing as shiiping'
         */
        if ($checkoutData->hasShippingAddress()) {
            $quote->setTotalsCollectedFlag(false);
            $quote->getShippingAddress()->addData(
                $this->addressDataTransform->prepareMagentoAddress($shippingAddress)
            );
        }
    }


    /**
     * @param $checkoutId
     * @param string $message
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    private function setValidateFailedResponse($checkoutId, $message = null)
    {
        return $this->resultRedirectFactory->create()
            ->setHttpResponseCode(303)
            ->setStatusHeader(303, null, $message)
            ->setPath(
                'checkout/klarna/validateFailed',
                [
                    '_nosid'  => true,
                    '_escape' => false,
                    '_query'  => ['id' => $checkoutId, 'message' => $message]
                ]
            );
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
