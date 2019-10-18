<?php
namespace Kodbruket\VsfKco\Api\Data;

/**
 * Interface ResponseValidateInterface
 * @package Kodbruket\VsfKco\Api\Data
 */
interface ResponseValidateInterface
{
    /**
     *
     */
    CONST IS_UPDATED_KLARNA = 'is_updated_klarna';

    /**
     *
     */
    CONST IS_UPDATED_M2     = 'is_updated_m2';

    /**
     *
     */
    CONST QUOTE_DATA = 'quote_data';

    /**
     *
     */
    CONST QUOTE_TOTAL_DATA = 'quote_total_data';

    /**
     * @param boolean $value
     * @return boolean
     */
    public function setIsUpdatedKlarna($value);

    /**
     * @return boolean
     */
    public function getIsUpdatedKlarna();

    /**
     * @param boolean $value
     * @return boolean
     */
    public function setIsUpdatedM2($value);

    /**
     * @return boolean
     */
    public function getIsUpdatedM2();


    /**
     * @param \Magento\Quote\Api\Data\CartInterface $value
     * @return mixed
     */
    public function setQuoteData($value);

    /**
     * @return \Magento\Quote\Api\Data\CartInterface
     */
    public function getQuoteData();

    /**
     * @param \Magento\Quote\Api\Data\TotalsInterface $value Quote totals data
     * @return mixed
     */
    public function setQuoteTotalData($value);

    /**
     * @return \Magento\Quote\Api\Data\TotalsInterface
     */
    public function getQuoteTotalData();
}
