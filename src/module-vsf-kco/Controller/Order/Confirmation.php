<?php
namespace Kodbruket\VsfKco\Controller\Order;

use Klarna\Core\Api\OrderRepositoryInterface;
use Klarna\Core\Helper\ConfigHelper;
use Klarna\Core\Model\OrderFactory;
use Klarna\Ordermanagement\Api\ApiInterface;
use Klarna\Ordermanagement\Model\Api\Ordermanagement;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\QuoteManagement;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Confirmation extends Action implements CsrfAwareActionInterface
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
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

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
        ScopeConfigInterface $scopeConfig,
        ConfigHelper $helper
    ) {
        $this->logger = $logger;
        $this->klarnaOrderFactory = $klarnaOrderFactory;
        $this->klarnaOrderRepository = $klarnaOrderRepository;
        $this->quoteManagement = $quoteManagement;
        $this->cartRepository = $cartRepository;
        $this->orderManagement = $orderManagement;
        $this->storeManager = $storeManager;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->scopeConfig = $scopeConfig;
        $this->helper = $helper;

        parent::__construct(
            $context
        );
    }

    public function execute()
    {
        $klarnaOrderId = $this->getRequest()->getParam('id');

        if (!$klarnaOrderId) {
            $this->logger->info('Klarna order ID is required for confirmation.');
            return;
        }

        $this->logger->info('Confirmation for Klarna order ID: ' . $klarnaOrderId);

        $store = $this->storeManager->getStore();
        $klarnaOrder = $this->klarnaOrderRepository->getByKlarnaOrderId($klarnaOrderId);

        if ($klarnaOrder->getOrderId()) {
            $this->logger->info('Order already exists in Magento for Klarna order ID: ' . $klarnaOrder->getOrderId());
            return;
        }

        if ($klarnaOrder->getIsAcknowledged()) {
            $this->logger->info('Klarna order ID ' . $klarnaOrderId . ' has already been acknowledged.');
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

        $store = $quote->getStoreId();
        $user = $this->helper->getApiConfig('merchant_id', $store);
        $password = $this->helper->getApiConfig('shared_secret', $store);
        $testMode = $this->helper->isApiConfigFlag('test_mode', $store);

        if ($testMode) {
            $url = 'https://api.playground.klarna.com/checkout/v3/orders/';
        } else {
            $url = 'https://api.klarna.com/checkout/v3/orders/';
        }

        $auth = base64_encode(sprintf("%s:%s", $user, $password));
        $context = stream_context_create([
            "http" => [
                "header" => "Authorization: Basic $auth"
            ]
        ]);
        $result = file_get_contents(
            $url . $klarnaOrderId,
            false,
            $context
        );
        $kco = json_decode($result, true);

        if ($shippingMethod = $kco['selected_shipping_option']) {
            $shippingMethodString = json_encode($shippingMethod, JSON_UNESCAPED_UNICODE);

            if ($quote->getExtShippingInfo() != $shippingMethodString) {
                $quote->setExtShippingInfo($shippingMethodString);
                $quote->save();
            }
        }

        $resultRedirect = $this->resultRedirectFactory->create();

        if ($quote->getId()) {
            try {
                $order = $this->quoteManagement->submit($quote);
                $orderId = $order->getId();
                if ($orderId) {
                    $klarnaOrder->setOrderId($orderId)->save();
                }
                $this->logger->info('Magento order created with ID ' . $order->getIncrementId());
                $successUrl = $this->scopeConfig->getValue('klarna/vsf/successful_link', ScopeInterface::SCOPE_STORES, $store);
                $resultRedirect->setUrl($successUrl.'?sid='.$klarnaOrderId);
                return $resultRedirect;

            } catch (\Exception $exception) {
                $this->logger->critical('Create order error' . $exception->getMessage());
                $this->orderManagement->cancel($klarnaOrderId);
            }
        }

        $failedUrl = $this->scopeConfig->getValue('klarna/vsf/failed_link', ScopeInterface::SCOPE_STORES, $store);
        $resultRedirect->setUrl($failedUrl);

        return $resultRedirect;
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
