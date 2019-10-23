<?php
namespace Kodbruket\VsfKco\Model\Data;

/**
 * Class ResponseRepository
 * @package Kodbruket\VsfKco\Model\Data
 */
class ResponseRepository extends \Magento\Framework\Model\AbstractModel
    implements \Kodbruket\VsfKco\Api\Data\ResponseValidateInterface
{
    /**
     * @return bool|mixed
     */
    public function getError()
    {
        return $this->getData(self::ERROR);
    }

    /**
     * @param bool $value
     * @return bool|ResponseRepository
     */
    public function setError($value)
    {
        return $this->setData(self::ERROR, $value);
    }

    /**
     * @return mixed|string
     */
    public function getMessage()
    {
        return $this->getData(self::MESSAGE);
    }

    /**
     * @param string $value
     * @return bool|ResponseRepository
     */
    public function setMessage($value)
    {
        return $this->setData(self::MESSAGE, $value);
    }

    /**
     * @return bool|mixed
     */
    public function getIsUpdatedKlarna()
    {
        return $this->getData(self::IS_UPDATED_KLARNA);
    }

    /**
     * @param bool $value
     * @return bool|ResponseRepository
     */
    public function setIsUpdatedKlarna($value)
    {
        return $this->setData(self::IS_UPDATED_KLARNA, $value);
    }

    /**
     * @return bool|mixed
     */
    public function getIsUpdatedM2()
    {
        return $this->getData(self::IS_UPDATED_M2);
    }

    /**
     * @param bool $value
     * @return bool|ResponseRepository
     */
    public function setIsUpdatedM2($value)
    {
        return $this->setData(self::IS_UPDATED_M2, $value);
    }

    /**
     * @return \Magento\Quote\Api\Data\CartInterface|mixed
     */
    public function getQuoteData()
    {
        return $this->getData(self::QUOTE_DATA);
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface $value
     * @return ResponseRepository|mixed
     */
    public function setQuoteData($value)
    {
        return $this->setData(self::QUOTE_DATA, $value);
    }

    /**
     * @return \Magento\Quote\Api\Data\TotalsInterface|mixed
     */
    public function getQuoteTotalData()
    {
        return $this->getData(self::QUOTE_TOTAL_DATA);
    }

    /**
     * @param \Magento\Quote\Api\Data\TotalsInterface $value
     * @return ResponseRepository|mixed
     */
    public function setQuoteTotalData($value)
    {
        return $this->setData(self::QUOTE_TOTAL_DATA, $value);
    }
}
