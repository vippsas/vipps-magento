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

namespace Vipps\Payment\Plugin\Sales\Model\Order;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;
use Magento\Sales\Model\Order\Payment as MagentoPayment;
use Vipps\Payment\Gateway\Command\PaymentDetailsProvider;
use Vipps\Payment\Gateway\Exception\VippsException;
use Vipps\Payment\Gateway\Transaction\TransactionBuilder;
use Vipps\Payment\Model\Order\PartialVoid\Config as PartialVoidConfig;

/**
 * Class Payment
 * @package Vipps\Payment\Plugin\Sales\Model\Order
 */
class Payment
{
    /**
     * @var PaymentDataObjectFactoryInterface
     */
    private $dataObjectFactory;

    /**
     * @var PaymentDetailsProvider
     */
    private $paymentDetailsProvider;

    /**
     * @var TransactionBuilder
     */
    private $transactionBuilder;

    /**
     * @var PartialVoidConfig
     */
    private $config;

    /**
     * Payment constructor.
     *
     * @param PaymentDataObjectFactoryInterface $dataObjectFactory
     * @param PaymentDetailsProvider $paymentDetailsProvider
     * @param TransactionBuilder $transactionBuilder
     * @param PartialVoidConfig $config
     */
    public function __construct(
        PaymentDataObjectFactoryInterface $dataObjectFactory,
        PaymentDetailsProvider $paymentDetailsProvider,
        TransactionBuilder $transactionBuilder,
        PartialVoidConfig $config
    ) {
        $this->dataObjectFactory = $dataObjectFactory;
        $this->paymentDetailsProvider = $paymentDetailsProvider;
        $this->transactionBuilder = $transactionBuilder;
        $this->config = $config;
    }

    /**
     * Throw exception if offline partial void disabled
     *
     * @param MagentoPayment $payment
     *
     * @throws LocalizedException
     * @throws VippsException
     */
    public function beforeCancel(MagentoPayment $payment)
    {
        if ($payment->getMethod() === 'vipps') {
            $paymentDataObject = $this->dataObjectFactory->create($payment);
            $response = $this->paymentDetailsProvider->get(['payment' => $paymentDataObject]);
            $transaction = $this->transactionBuilder->setData($response)->build();
            $offlineVoidEnabled = $this->config->isOfflinePartialVoidEnabled();
            if (!$offlineVoidEnabled && $transaction->getTransactionSummary()->getCapturedAmount() > 0) {
                throw new LocalizedException(__('Can\'t cancel captured transaction.'));
            }
        }
    }
}
