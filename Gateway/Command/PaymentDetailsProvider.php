<?php
/**
 * Copyright 2018 Vipps
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

use Vipps\Payment\Api\CommandManagerInterface;
use Vipps\Payment\Gateway\Exception\VippsException;
use Vipps\Payment\Gateway\Transaction\Transaction;
use Vipps\Payment\Gateway\Transaction\TransactionBuilder;

/**
 * Class PaymentDetailsProvider
 * @package Vipps\Payment\Gateway\Command
 * @spi
 */
class PaymentDetailsProvider
{
    /**
     * @var CommandManagerInterface
     */
    private $commandManager;

    /**
     * @var TransactionBuilder
     */
    private $transactionBuilder;

    /**
     * @var array
     */
    private $cache = [];

    /**
     * PaymentDetailsProvider constructor.
     *
     * @param CommandManagerInterface $commandManager
     * @param TransactionBuilder $transactionBuilder
     */
    public function __construct(
        CommandManagerInterface $commandManager,
        TransactionBuilder $transactionBuilder
    ) {
        $this->commandManager = $commandManager;
        $this->transactionBuilder = $transactionBuilder;
    }

    /**
     * @param string $orderId
     *
     * @return Transaction
     * @throws VippsException
     */
    public function get($orderId)
    {
        if (!isset($this->cache[$orderId])) {
            $response = $this->commandManager->getPaymentDetails(['orderId' => $orderId]);
            $transaction = $this->transactionBuilder->setData($response)->build();

            $this->cache[$orderId] = $transaction;
        }

        return $this->cache[$orderId];
    }
}
