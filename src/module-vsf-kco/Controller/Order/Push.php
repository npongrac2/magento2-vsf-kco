<?php
namespace Kodbruket\VsfKco\Controller\Order;

use Klarna\Core\Api\OrderRepositoryInterface;
use Klarna\Core\Helper\ConfigHelper;
use Klarna\Core\Model\OrderFactory;
use Klarna\Ordermanagement\Api\ApiInterface;
use Klarna\Ordermanagement\Model\Api\Ordermanagement;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\QuoteManagement;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface as MageOrderRepositoryInterface;
use Psr\Log\LoggerInterface;


class Push extends Action implements CsrfAwareActionInterface
{

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OrderFactory
     */
    private $klarnaOrderFactory;

    /**
     * @var OrderRepositoryInterface
     */
    private $klarnaOrderRepository;

    /**
     * @var QuoteManagement
     */
    private $quoteManagement;


    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var ApiInterface
     */
    private $orderManagement;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var MageOrderRepositoryInterface
     */
    private $mageOrderRepository;

    /**
     * Push constructor.
     * @param Context $context
     * @param LoggerInterface $logger
     * @param OrderFactory $klarnaOrderFactory
     * @param OrderRepositoryInterface $klarnaOrderRepository
     * @param QuoteManagement $quoteManagement
     * @param CartRepositoryInterface $cartRepository
     * @param Ordermanagement $orderManagement
     * @param StoreManagerInterface $storeManager
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param MageOrderRepositoryInterface $mageOrderRepository
     */

    public function __construct(
        Context $context,
        LoggerInterface $logger,
        OrderFactory $klarnaOrderFactory,
        OrderRepositoryInterface $klarnaOrderRepository,
        QuoteManagement $quoteManagement,
        CartRepositoryInterface $cartRepository,
        Ordermanagement $orderManagement,
        StoreManagerInterface $storeManager,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        MageOrderRepositoryInterface $mageOrderRepository
    ) {
        $this->logger = $logger;
        $this->klarnaOrderFactory = $klarnaOrderFactory;
        $this->klarnaOrderRepository = $klarnaOrderRepository;
        $this->quoteManagement = $quoteManagement;
        $this->cartRepository = $cartRepository;
        $this->orderManagement = $orderManagement;
        $this->storeManager = $storeManager;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->mageOrderRepository = $mageOrderRepository;
        parent::__construct(
            $context
        );
    }

    public function execute()
    {
        $klarnaOrderId = $this->getRequest()->getParam('id');
        $this->logger->info('Pussing Klarna Order Id: ' . $klarnaOrderId);
        $store = $this->storeManager->getStore();
        if (!$klarnaOrderId) {
            echo 'Klarna Order ID is required';
            return;
        }
        $klarnaOrder = $this->klarnaOrderRepository->getByKlarnaOrderId($klarnaOrderId);

        if ($klarnaOrder->getIsAcknowledged()) {
            echo 'Error: Order ' . $klarnaOrderId . ' has been acknowledged';
            return;
        }

        $this->orderManagement->resetForStore($store, ConfigHelper::KCO_METHOD_CODE);
        $placedKlarnaOrder = $this->orderManagement->getPlacedKlarnaOrder($klarnaOrderId);

        $maskedId = $placedKlarnaOrder->getDataByKey('merchant_reference2');
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($maskedId, 'masked_id');
        $quoteId = $quoteIdMask->getQuoteId();
        if( (int)$quoteId==0 && ctype_digit(strval($maskedId))){
            $quoteId = (int)$maskedId;
        }
        $quote = $this->cartRepository->get($quoteId);
        if (!$quote->getId()) {
            echo 'Quote is not existed in Magento';
        }

        if ($klarnaOrder->getOrderId()) {
            $this->acknowledgeOrder($klarnaOrderId, $klarnaOrder->getOrderId(), $quoteId);
            return;
        } else {
            try {
                $order = $this->quoteManagement->submit($quote);
                $this->acknowledgeOrder($klarnaOrderId, $order->getId(), $quoteId);
                echo 'Magento order created with ID ' . $order->getIncrementId();
                return;
            } catch (\Exception $exception) {
                echo 'Create order error: ' . $exception->getMessage();
                //Cancel Klarna Order if can not create order from merchant
                $this->orderManagement->cancel($klarnaOrderId);
                return;
            }
        }
        exit;
    }

    private function acknowledgeOrder($klarnaOrderId, $orderId, $quoteId)
    {
        if ($klarnaOrderId && $orderId && $quoteId) {
            try {
                $mageOrder = $this->mageOrderRepository->get($orderId);
                $klarnaOrder = $this->klarnaOrderRepository->getByKlarnaOrderId($klarnaOrderId);
                $this->orderManagement->updateMerchantReferences($klarnaOrderId, $mageOrder->getIncrementId(), $quoteId);
                $this->orderManagement->acknowledgeOrder($klarnaOrderId);
                $klarnaOrder->setOrderId($orderId)
                    ->setIsAcknowledged(true)
                    ->save();
                echo 'Sent ACK successfully with Klarna ID: ' . $klarnaOrderId;
                return;
            } catch (\Exception $exception) {
                echo 'Send ACK error: ' . $exception->getMessage();
            }
        } else {
            echo 'Something went wrong when sending ACK';
        }
        return;
    }

    /**
     * Create CSRF validation exception
     *
     * @param RequestInterface $request
     *
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Validate for CSRF
     *
     * @param RequestInterface $request
     *
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
