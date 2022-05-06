<?php
/**
 * Copyright 2020 Vipps
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
 * documentation files (the "Software"), to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software,
 * and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED
 * TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
 * CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 */
namespace Vipps\Payment\Model\Logger\Handler;

use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;
use Magento\Payment\Gateway\ConfigInterface;
use Monolog\Logger;

/**
 * Class Debug
 * @package Vipps\Payment\Model\Logger\Handler
 */
class Debug extends Base
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/vipps_debug.log'; //@codingStandardsIgnoreLine

    /**
     * @var int
     */
    protected $loggerType = Logger::DEBUG; //@codingStandardsIgnoreLine

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * Debug constructor.
     *
     * @param DriverInterface $filesystem
     * @param ConfigInterface|null $config
     * @param string|null $filePath
     */
    public function __construct(
        DriverInterface $filesystem,
        ConfigInterface $config = null,
        string $filePath = null
    ) {
        parent::__construct($filesystem, $filePath);
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $record
     *
     * @return bool
     */
    public function isHandling(array $record): bool
    {
        if ($this->config && (bool)$this->config->getValue('debug')) {
            return parent::isHandling($record);
        }
        return false;
    }
}
