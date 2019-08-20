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
declare(strict_types=1);

namespace Vipps\Payment\Gateway\Config;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Config\ValueHandlerInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Vipps\Payment\Gateway\Command\PaymentDetailsProvider;
use Vipps\Payment\Gateway\Exception\VippsException;
use Vipps\Payment\Gateway\Transaction\TransactionBuilder;

/**
 * Class CanVoidHandler
 * @package Vipps\Payment\Gateway\Config
 */
class CanVoidHandler implements ValueHandlerInterface
{
    /**
     * @var Config
     */
    private $gatewayConfig;

    /**
     * @var PaymentDetailsProvider
     */
    private $paymentDetailsProvider;

    /**
     * @var TransactionBuilder
     */
    private $transactionBuilder;

    /**
     * CanVoidHandler constructor.
     *
     * @param Config $gatewayConfig
     * @param PaymentDetailsProvider $paymentDetailsProvider
     * @param TransactionBuilder $transactionBuilder
     */
    public function __construct(
        Config $gatewayConfig,
        PaymentDetailsProvider $paymentDetailsProvider,
        TransactionBuilder $transactionBuilder
    ) {
        $this->gatewayConfig = $gatewayConfig;
        $this->paymentDetailsProvider = $paymentDetailsProvider;
        $this->transactionBuilder = $transactionBuilder;
    }

    /**
     * Disable partial online void
     *
     * @param array $subject
     * @param null $storeId
     *
     * @return bool
     * @throws NoSuchEntityException
     * @throws VippsException
     */
    public function handle(array $subject, $storeId = null): bool
    {
        $response = $this->paymentDetailsProvider->get($subject);
        $transaction = $this->transactionBuilder->setData($response)->build();
        if ($transaction->getTransactionSummary()->getCapturedAmount() > 0) {
            return false;
        }

        return (bool)$this->gatewayConfig->getValue(SubjectReader::readField($subject), $storeId);
    }
}
