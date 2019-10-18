<?php
namespace Kodbruket\VsfKco\Api;

/**
 * Interface ValidateQuoteInterface
 * @package Kodbruket\VsfKco\Api
 */
interface ValidateQuoteInterface
{

    /**
     * @param \Kodbruket\VsfKco\Api\Data\ValidateQuoteRequestInterface $quote
     * @return \Kodbruket\VsfKco\Api\Data\ResponseValidateInterface
     * @throws \Exception
     */
    public function validateCallBack($quote);
}


