<?php
namespace Kodbruket\VsfKco\Model;
use Klarna\Core\Model\Api\Builder;
use Klarna\Core\Api\ServiceInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\DataObject;
use Klarna\Kp\Api\Data\RequestInterface;

/**
 * Class ValidateQuoteRepository
 * @package Kodbruket\VsfKco\Model
 */
class ValidateQuoteRepository implements \Kodbruket\VsfKco\Api\ValidateQuoteInterface
{
    /**
     * @var \Magento\Framework\Webapi\Rest\Request
     */
    private $request;

    /**
     * @var \Magento\Quote\Api\CartTotalManagementInterface
     */
    private $cartTotalManagement;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;

    /**
     * @var \Kodbruket\VsfKco\Api\Data\ResponseValidateInterfaceFactory
     */
    private $responseValidateInterfaceFactory;

    /**
     * @var \Klarna\Ordermanagement\Model\Api\Rest\Service\Ordermanagement
     */
    private $klanaOrdermanagement;

    /**
     * @var \Magento\Framework\DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var \Klarna\Kp\Model\Api\Builder\Kasper
     */
    private $klarnaApiBuilder;

    /**
     * @var ServiceInterface
     */
    private $klarnaApiService;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ScopeInterface
     */
    private $scopeConfig;

    /**
     * @var \Kodbruket\VsfKco\Logger\Logger
     */
    private $logger;

    /**
     * @var \Klarna\Core\Helper\KlarnaConfig
     */
    private $klarnaConfig;

    /**
     * @var \Klarna\Core\Helper\VersionInfo
     */
    private $versionInfo;

    /**
     * @var \Magento\Store\Api\Data\StoreInterface
     */
    private $store;

    /**
     * ValidateQuoteRepository constructor.
     * @param \Magento\Framework\Webapi\Rest\Request $request
     * @param \Magento\Quote\Api\CartTotalRepositoryInterface $cartTotalManagement
     * @param \Magento\Quote\Api\CartRepositoryInterface $cartRepository
     * @param \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param \Magento\Framework\DataObjectFactory $dataObjectFactory
     * @param \Kodbruket\VsfKco\Api\Data\ResponseValidateInterfaceFactory $responseValidateInterfaceFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Klarna\Ordermanagement\Model\Api\Rest\Service\Ordermanagement $klanaOrdermanagement
     * @param \Klarna\Kp\Model\Api\Builder\Kasper $klarnaApiBuilder
     * @param ServiceInterface $klarnaApiService
     * @param \Kodbruket\VsfKco\Logger\Logger $logger
     * @param \Klarna\Core\Helper\KlarnaConfig $klarnaConfig
     * @param \Klarna\Core\Helper\VersionInfo $versionInfo
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function __construct(
        \Magento\Framework\Webapi\Rest\Request $request,
        \Magento\Quote\Api\CartTotalRepositoryInterface $cartTotalManagement,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepository,
        \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        \Magento\Framework\DataObjectFactory $dataObjectFactory,
        \Kodbruket\VsfKco\Api\Data\ResponseValidateInterfaceFactory $responseValidateInterfaceFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Klarna\Ordermanagement\Model\Api\Rest\Service\Ordermanagement $klanaOrdermanagement,
        \Klarna\Kp\Model\Api\Builder\Kasper $klarnaApiBuilder,
        \Klarna\Core\Api\ServiceInterface $klarnaApiService,
        \Kodbruket\VsfKco\Logger\Logger $logger,
        \Klarna\Core\Helper\KlarnaConfig $klarnaConfig,
        \Klarna\Core\Helper\VersionInfo $versionInfo
    )
    {
        $this->request                          = $request;
        $this->cartTotalManagement              = $cartTotalManagement;
        $this->cartRepository                   = $cartRepository;
        $this->maskedQuoteIdToQuoteId           = $maskedQuoteIdToQuoteId;
        $this->responseValidateInterfaceFactory = $responseValidateInterfaceFactory;
        $this->dataObjectFactory                = $dataObjectFactory;
        $this->klanaOrdermanagement             = $klanaOrdermanagement;
        $this->klarnaApiBuilder                 = $klarnaApiBuilder;
        $this->klarnaApiService                 = $klarnaApiService;
        $this->storeManager                     = $storeManager;
        $this->scopeConfig                      = $scopeConfig;
        $this->logger                           = $logger;
        $this->klarnaConfig                     = $klarnaConfig;
        $this->versionInfo                      = $versionInfo;
        $this->store                            = $storeManager->getStore();
    }

    /**
     * @param \Kodbruket\VsfKco\Api\Data\ValidateQuoteRequestInterface $quote
     * @return array|\Kodbruket\VsfKco\Api\Data\ResponseValidateInterface|DataObject
     */
    public function validateCallBack($quote)
    {
        $isUpdatedKlarna = $isUpdatedM2 = false;

        $cartId = $this->request->getParam('cartId');

        $klarnaOrderId = $quote->getKlarnaOrderId();

        try {

            /** Fetch order from Klarna to check with Magento 2 */
            $this->connect();
            $url = "/checkout/v3/orders/{$klarnaOrderId}";
            $klarnaOrder = $this->klarnaApiService->makeRequest(
                $url,
                '',
                ServiceInterface::GET,
                $klarnaOrderId
            );

            $klarnaOrder = $this->dataObjectFactory->create(['data' => $klarnaOrder]);

            if ( $klarnaOrder->getStatus() !== 'checkout_incomplete' ) {
                $response = [
                    'error' => true,
                    'message' => __('Klarna Order couldn\'t update, the order already complete.')
                ];
                $this->logger->error('Klarna Order couldn\'t update, the order already complete - klarnaOrderId: '.$klarnaOrderId);
                return new DataObject($response);
            }

            if (!is_numeric($cartId)) {
                $cartId = $this->maskedQuoteIdToQuoteId->execute($cartId);
            }

            $quoteM2 = $this->cartRepository->get($cartId);

            $m2Total = $this->cartTotalManagement->get($cartId);

            $m2OrderAmount = intval(round($m2Total->getGrandTotal() * 100));

            $klarnaOrderAmount = $klarnaOrder->getOrderAmount();

        }catch (\Exception $e) {
            $response = [
                'error' => true,
                'message' => $e->getMessage()
            ];
            $this->logger->error( $e->getMessage().' - klarnaOrderId: '.$klarnaOrderId);
            return new DataObject($response);
        }

        if ( $klarnaOrderAmount == $m2OrderAmount ) {
            /** @todo: some custom logic comes here */
            $this->logger->info('Klarna and Quote Id have same amount - KlarnaOrderId: ', $klarnaOrderId .' quoteId: '.$quoteM2->getId());
        }else {

            try {
                /** Update Klarna order from Magento 2 data if there are passed salesrules */
                if ( !empty($quoteM2->getAppliedRuleIds()) ) {

                    /** @var RequestInterface $data */
                    $data = $this->klarnaApiBuilder->setObject($quoteM2)
                        ->generateRequest(Builder::GENERATE_TYPE_UPDATE)
                        ->getRequest();

                    /** Make request to send to Klarna */
                    $this->klarnaApiService->makeRequest(
                        "/checkout/v3/orders/{$klarnaOrderId}",
                        $data,
                        ServiceInterface::POST,
                        $klarnaOrderId
                    );

                    $isUpdatedKlarna = true;

                    $this->logger->info('Update Klarna Order From M2 - klarnaOrderId: '.$klarnaOrderId. ' - M2 quote Id: '.$quoteM2->getId());

                } else {
                    /** Pull Klarna shipping info to m2 quote */
                    $this->updatedM2QuoteFromKlarnaOrder( $quoteM2, $klarnaOrder );

                    $this->logger->info('Update M2 Quote from Klarna Order - klarnaOrderId: '.$klarnaOrderId.' - M2 quote Id: '.$quoteM2->getId());

                    $isUpdatedM2 = true;
                }
            }catch (\Exception $e) {
                $response = [
                    'error' => true,
                    'message' => $e->getMessage()
                ];
                $this->logger->error( $e->getMessage().' - klarnaOrderId: '.$klarnaOrderId);
                return new DataObject($response);
            }
        }

        $response = $this->responseValidateInterfaceFactory->create()
            ->setData([
            'error'             => false,
            'message'           => __('Callback to M2 was triggered with klarnaOrderId: '. $klarnaOrderId .' and M2 quote Id:'. $quoteM2->getId()),
            'is_updated_m2'     => $isUpdatedM2,
            'is_updated_klarna' => $isUpdatedKlarna,
            'quote_data'        => $quoteM2,
            'quote_total_data'  => $m2Total
        ]);

        return $response;
    }

    /**
     * @param $carrierClass
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getShippingFromKSSCarrierClass($carrierClass) {
        $store = $this->storeManager->getStore();
        $mappings = $this->scopeConfig->getValue('klarna/vsf/carrier_mapping', ScopeInterface::SCOPE_STORES, $store);
        if ($mappings) {
            $mappings = json_decode($mappings, true);
            foreach($mappings as $item) {
                if($item['kss_carrier'] == $carrierClass) {
                    return $item['shipping_method'];
                }
            }
        }
        return '';
    }

    /**
     * @param DataObject $checkoutData
     * @return bool|mixed
     */
    private function getShippingMedthodFromOrderLines(DataObject $checkoutData)
    {
        $orderLines = $checkoutData->getData('order_lines');

        if (is_array($orderLines)) {
            foreach ($orderLines as $line) {
                if (isset($line['type']) && $line['reference'] && $line['type'] === 'shipping_fee') {
                    return $line;
                }
            }
        }
        return false;
    }

    /**
     * @param $shippingCode
     * @return string
     */
    private function convertShippingMethodCode($shippingCode)
    {
        if (!strpos($shippingCode, '_')) return $shippingCode . '_' . $shippingCode;
        return $shippingCode;
    }

    /**
     * @param $m2Quote
     * @param $klarnaOrder
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function updatedM2QuoteFromKlarnaOrder($m2Quote, $klarnaOrder) {

        $shippingDescription = null;

        if ($shippingMethod = $klarnaOrder->getData('selected_shipping_option')) {

            $shippingMethodString = json_encode($shippingMethod, JSON_UNESCAPED_UNICODE);

            $m2Quote->setExtShippingInfo($shippingMethodString);

            if (empty($shippingMethod) || !(array_key_exists('carrier', $shippingMethod['delivery_details']) && array_key_exists('class', $shippingMethod['delivery_details']))) {
                $shippingMethodCode = $shippingMethod['id'];
            } else {
                $shippingMethodCode = $this->getShippingFromKSSCarrierClass($shippingMethod['delivery_details']['carrier'] . '_' . $shippingMethod['delivery_details']['class']);
            }

            $shippingDescription = $shippingMethod['name'];
        } else {
            if ($shippingMethod = $this->getShippingMedthodFromOrderLines($klarnaOrder)) {
                $shippingMethodCode = $shippingMethod['reference'];
            }
        }

        /**
         *  set selectedShippingMethod from Klarna to Magento2
         */
        if (isset($shippingMethodCode)) {
            $shippingMethodCode = $this->convertShippingMethodCode($shippingMethodCode);
            $m2Quote->getShippingAddress()
                ->setShippingMethod($shippingMethodCode)
                ->setShippingDescription($shippingDescription)
                ->setCollectShippingRates(true)
                ->collectShippingRates();
        }

        $m2Quote->setTotalsCollectedFlag(false)
            ->collectTotals()
            ->save();

        return $m2Quote;
    }

    /**
     *
     */
    private function connect()
    {
        $version = sprintf(
            '%s;Core/%s;OM/%s',
            $this->versionInfo->getVersion('Klarna_Kp'),
            $this->versionInfo->getVersion('Klarna_Core'),
            $this->versionInfo->getVersion('Klarna_Ordermanagement')
        );
        $mageMode = $this->versionInfo->getMageMode();
        $mageVersion = $this->versionInfo->getMageEdition() . '/' . $this->versionInfo->getMageVersion();
        $mageInfo = "Magento {$mageVersion} {$mageMode} mode";
        $this->klarnaApiService->setUserAgent('Magento2_KP', $version, $mageInfo);
        $this->klarnaApiService->setHeader('Accept', '*/*');

        $username = $this->scopeConfig->getValue('klarna/api/merchant_id', ScopeInterface::SCOPE_STORES, $this->store);
        $password = $this->scopeConfig->getValue('klarna/api/shared_secret', ScopeInterface::SCOPE_STORES, $this->store);
        $test_mode = $this->scopeConfig->getValue('klarna/api/test_mode', ScopeInterface::SCOPE_STORES, $this->store);

        $versionConfig = $this->klarnaConfig->getVersionConfig($this->store);
        $url = $versionConfig->getUrl($test_mode);

        $this->klarnaApiService->connect($username, $password, $url);
        return $this;
    }
}
