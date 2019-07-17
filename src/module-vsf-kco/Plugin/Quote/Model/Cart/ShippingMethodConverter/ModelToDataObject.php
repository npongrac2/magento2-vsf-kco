<?php
namespace Kodbruket\VsfKco\Plugin\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Framework\Pricing\PriceCurrencyInterface;

/**
 * Class ModelToDataObject
 * @package Kodbruket\VsfKco\Plugin\Quote\Model\Cart\ShippingMethodConverter
 */
class ModelToDataObject
{
    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * ModelToDataObject constructor.
     * @param PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        PriceCurrencyInterface $priceCurrency
    ) {
        $this->priceCurrency = $priceCurrency;
    }

    /**
     * We had a problem when M2 Rest API returns the same values for
     * price_excl_tax and price_incl_tax, that's cause issues in KCO
     * This is workaround for unblock KCO testing.
     * Todo: REMOVE this class and di.xml declaration after find root cost of the problem.
     *
     * @param \Magento\Quote\Model\Cart\ShippingMethodConverter $context
     * @param \Closure $proceed
     * @param \Magento\Quote\Model\Quote\Address\Rate $rateModel
     * @param string $quoteCurrencyCode
     * @return \Magento\Quote\Api\Data\ShippingMethodInterface
     */
    public function aroundModelToDataObject($context, $proceed, $rateModel, $quoteCurrencyCode)
    {
        /** @var \Magento\Quote\Api\Data\ShippingMethodInterface $result */
        $result = $proceed();

        return $result;

        /**
         * Agree that we don't have setup 0% tax , so it's not our case.
         */
        if ($result->getPriceInclTax() != $result->getPriceExclTax()) {
            return $result;
        }

        $result->setPriceExclTax(
            $this->calculateTaxRate($rateModel, $result->getPriceExclTax())
        );

        return $result;
    }
}
