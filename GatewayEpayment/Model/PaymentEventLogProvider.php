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

namespace Vipps\Payment\GatewayEpayment\Model;

use Vipps\Checkout\Api\PaymentCommandManagerInterface;
use Vipps\Checkout\Gateway\Data\PaymentEventLogBuilder;
use Vipps\Checkout\Gateway\Exception\VippsException;
use Vipps\Checkout\Gateway\Data\PaymentEventLog;

/**
 * Class PaymentEventLogProvider
 * @package Vipps\Checkout\Model
 * @spi
 */
class PaymentEventLogProvider
{
    /**
     * @var PaymentCommandManagerInterface
     */
    private $commandManager;

    /**
     * @var PaymentEventLogBuilder
     */
    private $paymentEventLogBuilder;

    /**
     * @var array
     */
    private $cache = [];

    /**
     * PaymentEventLogProvider constructor.
     *
     * @param PaymentCommandManagerInterface $commandManager
     * @param PaymentEventLogBuilder $paymentEventLogBuilder
     */
    public function __construct(
        PaymentCommandManagerInterface $commandManager,
        PaymentEventLogBuilder $paymentEventLogBuilder
    ) {
        $this->commandManager = $commandManager;
        $this->paymentEventLogBuilder = $paymentEventLogBuilder;
    }

    /**
     * @param string $orderId
     *
     * @throws VippsException
     */
    public function get($orderId): PaymentEventLog
    {
        if (!isset($this->cache[$orderId])) {
            $response = $this->commandManager->getPaymentEventLog($orderId);
            $eventLog = $this->paymentEventLogBuilder->setData($response)->build();

            $this->cache[$orderId] = $eventLog;
        }

        return $this->cache[$orderId];
    }
}
