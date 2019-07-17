<?php
namespace Kodbruket\VsfKco\Plugin\Kp\Model\Payment\Kp;

use Kodbruket\VsfKco\Model\ExtensionConstants;

/**
 * Class IsAvailable
 * @package Kodbruket\VsfKco\Plugin\Kp\Model\Payment\Kp
 */
class IsAvailable
{
    /**
     * @param \Klarna\Kp\Model\Payment\Kp $context
     * @param \Closure $proceed
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @return int
     */
    public function aroundIsAvailable($context, $proceed, $quote)
    {
        return true;
    }
}
