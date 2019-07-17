<?php
namespace Kodbruket\VsfKco\Gateway\Command;
use Klarna\Core\Model\OrderFactory;
use Kodbruket\VsfKco\Model\ExtensionConstants;

/**
 * Class Authorize
 * @package Kodbruket\VsfKco\Gateway\Command
 */
class Authorize extends \Klarna\Kp\Gateway\Command\Authorize
{

    /**
     * @param array $commandSubject
     * @return \Magento\Payment\Gateway\Command\ResultInterface|null
     * @throws \Klarna\Core\Exception
     * @throws \Klarna\Core\Model\Api\Exception
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Payment\Gateway\Command\CommandException
     */
    public function execute(array $commandSubject)
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $commandSubject['payment']->getPayment();
        if (!$payment->getAdditionalInformation(ExtensionConstants::FORCE_ORDER_PLACE)) {
            return parent::execute($commandSubject);
        }

        $payment->setTransactionId(
            $payment->getAdditionalInformation(ExtensionConstants::KLARNA_ORDER_ID)
        )->setIsTransactionClosed(0);
    }

}
