<?php
/**
 * Copyright 2022 Vipps
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
namespace Vipps\Payment\Gateway\Command;

use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\CommandManagerInterface;
use Vipps\Payment\Gateway\Exception\VippsException;

/**
 * Class ReceiptSender
 * @package Vipps\Payment\Gateway\Command
 * @spi
 */
class ReceiptSender
{
    /**
     * @var CommandManagerInterface
     */
    private $commandManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * ReceiptSender constructor.
     *
     * @param CommandManagerInterface $commandManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        CommandManagerInterface $commandManager,
        LoggerInterface $logger
    ) {
        $this->commandManager = $commandManager;
        $this->logger = $logger;
    }

    /**
     * @param OrderInterface $order
     *
     * @return void
     * @throws VippsException
     */
    public function send(OrderInterface $order): void
    {
        try {
            $this->commandManager->sendReceipt($order);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }
}
