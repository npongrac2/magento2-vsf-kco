<?php
namespace Kodbruket\VsfKco\Model\Logger\Handler;

use Magento\Framework\Logger\Handler\Base;

/**
 * Class Debug
 * @package Kodbruket\VsfKco\Model\Logger\Handler
 */
class Debug extends Base
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/vsf_kco_callback.log';
}
