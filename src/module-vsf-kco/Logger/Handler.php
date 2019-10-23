<?php
namespace Kodbruket\VsfKco\Logger;

use Monolog\Logger;

/**
 * Class Handler
 * @package Kodbruket\VsfKco\Logger
 */
class Handler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * File name
     * @var string
     */
    protected $fileName = '/var/log/module_vsf_kco.log';
}
