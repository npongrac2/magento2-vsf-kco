<?php
namespace Kodbruket\VsfKco\Controller\Order;

use Klarna\Core\Api\OrderRepositoryInterface;
use Klarna\Core\Model\OrderFactory;
use Klarna\Ordermanagement\Api\ApiInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteManagement;
use Klarna\Ordermanagement\Model\Api\Ordermanagement;
use Magento\Store\Model\StoreManagerInterface;
use Klarna\Core\Helper\ConfigHelper;
use Magento\Quote\Model\QuoteIdMaskFactory;

use Psr\Log\LoggerInterface;


class Push extends Action
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
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->logger = $logger;
        $this->klarnaOrderFactory = $klarnaOrderFactory;
        $this->klarnaOrderRepository = $klarnaOrderRepository;
        $this->quoteManagement = $quoteManagement;
        $this->cartRepository = $cartRepository;
        $this->orderManagement = $orderManagement;
        $this->storeManager = $storeManager;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        parent::__construct(
            $context
        );
    }

    public function execute()
    {
        $klarnaOrderId = $this->getRequest()->getParam('id');
        $this->logger->info('Pussing Klarna Order Id: '.$klarnaOrderId);
        $store = $this->storeManager->getStore();
        if (!$klarnaOrderId) {
            $this->logger->info('Klarna Order ID is required');
            return;
        }
        $klarnaOrder = $this->klarnaOrderRepository->getByKlarnaOrderId($klarnaOrderId);

        if ($klarnaOrder->getOrderId()) {
            $this->logger->info('Error: Order already exists in Magento: ' . $klarnaOrder->getOrderId());
            return;
        }
        if ($klarnaOrder->getIsAcknowledged()) {
            $this->logger->info('Error: Order ' . $klarnaOrderId . ' has been acknowledged ');
            return;
        }
        $this->orderManagement->resetForStore($store, ConfigHelper::KCO_METHOD_CODE);
        $placedKlarnaOrder = $this->orderManagement->getPlacedKlarnaOrder($klarnaOrderId);

        $maskedId = $placedKlarnaOrder->getDataByKey('merchant_reference2');
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($maskedId, 'masked_id');
        $quoteId = $quoteIdMask->getQuoteId();
        $quote = $this->cartRepository->get($quoteId);
        if ($quote->getId()) {
            try {
                $order = $this->quoteManagement->submit($quote);
                $orderId = $order->getId();
                if ($orderId) {
                    $this->orderManagement->updateMerchantReferences($klarnaOrderId, $orderId, $quoteId);
                    $this->orderManagement->acknowledgeOrder($klarnaOrderId);
                    $klarnaOrder->setOrderId($orderId)
                        ->setIsAcknowledged(true)
                        ->save();
                }
                $this->logger->info('Magento order created with ID ' . $order->getIncrementId());

            } catch (\Exception $exception) {
                $this->logger->critical('validation save quote' . $exception->getMessage());
                $this->logger->critical('validation save quote' . $exception->getTraceAsString());
                return;
            }
        }
        exit;

    }


    private function getQuoteId()
    {
        $mask =  $this->getKlarnaRequestData()->getData(
            'merchant_reference2'
        );

        /** @var $quoteIdMask QuoteIdMask */
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($mask, 'masked_id');
        return $quoteIdMask->getQuoteId();
    }

}
