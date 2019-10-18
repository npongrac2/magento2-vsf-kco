<?php
namespace Kodbruket\VsfKco\Model\Data;

/**
 * Class ValidateQuoteRequestRepository
 * @package Kodbruket\VsfKco\Model\Data
 */
class ValidateQuoteRequestRepository extends \Magento\Framework\Model\AbstractModel
    implements \Kodbruket\VsfKco\Api\Data\ValidateQuoteRequestInterface
{
    /**
     * @return string
     */
    public function getKlarnaOrderId()
    {
        return $this->getData(self::KLARNA_ORDER_ID);
    }

    /**
     * @param $value
     * @return bool|ValidateQuoteRequestRepository
     */
    public function setKlarnaOrderId($value)
    {
        return $this->setData(self::KLARNA_ORDER_ID, $value);
    }
}
