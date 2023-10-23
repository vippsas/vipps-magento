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

namespace Vipps\Payment\GatewayEcomm\Model;

use Vipps\Payment\Api\Payment\CommandManagerInterface;
use Vipps\Payment\GatewayEcomm\Exception\VippsException;
use Vipps\Payment\GatewayEcomm\Data\Payment;
use Vipps\Payment\GatewayEcomm\Data\PaymentBuilder;

/**
 * Class PaymentProvider
 * @package Vipps\Checkout\Model
 * @spi
 */
class PaymentProvider
{
    /**
     * @var CommandManagerInterface
     */
    private $commandManager;

    /**
     * @var PaymentBuilder
     */
    private $paymentBuilder;

    /**
     * @var array
     */
    private $cache = [];

    /**
     * PaymentProvider constructor.
     *
     * @param CommandManagerInterface $commandManager
     * @param PaymentBuilder $paymentBuilder
     */
    public function __construct(
        CommandManagerInterface $commandManager,
        PaymentBuilder $paymentBuilder
    ) {
        $this->commandManager = $commandManager;
        $this->paymentBuilder = $paymentBuilder;
    }

    /**
     * @param string $orderId
     *
     * @return Payment
     * @throws VippsException
     */
    public function get(string $orderId): Payment
    {
        if (!isset($this->cache[$orderId])) {
            $response = $this->commandManager->getPayment($orderId);
            /** @var Payment $payment */
            $payment = $this->paymentBuilder->setData($response)->build();

            $this->cache[$orderId] = $payment;
        }

        return $this->cache[$orderId];
    }
}
