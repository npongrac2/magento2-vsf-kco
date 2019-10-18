<?php
namespace Kodbruket\VsfKco\Api\Data;

/**
 * Interface ValidateQuoteRequestInterface
 * @package Kodbruket\VsfKco\Api\Data
 */
interface ValidateQuoteRequestInterface
{
    /**
     *
     */
    CONST KLARNA_ORDER_ID = 'klarna_order_id';

    /**
     * @param $value
     * @return boolean
     */
    public function setKlarnaOrderId($value);

    /**
     * @return string
     */
    public function getKlarnaOrderId();
}
