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

namespace Vipps\Payment\GatewayEpayment\Command;

use Vipps\Payment\GatewayEpayment\Data\Payment;
use Vipps\Payment\GatewayEpayment\Data\PaymentBuilder;
use Vipps\Payment\GatewayEpayment\Data\Session;
use Vipps\Payment\Api\Payment\CommandManagerInterface;
use Vipps\Payment\GatewayEpayment\Exception\VippsException;
use Vipps\Payment\GatewayEpayment\Data\SessionBuilder;

/**
 * Class PaymentDetailsProvider
 * @package Vipps\Payment\GatewayEpayment\Command
 * @spi
 */
class PaymentDetailsProvider implements  \Vipps\Payment\Api\Transaction\PaymentDetailsInterface
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
     * PaymentDetailsProvider constructor.
     *
     * @param CommandManagerInterface $commandManager
     * @param SessionBuilder $paymentBuilder
     */
    public function __construct(
        CommandManagerInterface $commandManager,
        PaymentBuilder          $paymentBuilder
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
    public function get(string $orderId): ?Payment
    {
        if (!isset($this->cache[$orderId])) {

            $response = $this->commandManager->getPayment($orderId);
            $transaction = $this->paymentBuilder->setData($response)->build();

            $this->cache[$orderId] = $transaction;
        }

        return $this->cache[$orderId];
    }
}
