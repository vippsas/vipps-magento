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

namespace Vipps\Payment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;
use Magento\Sales\Model\Order;
use Vipps\Payment\Gateway\Command\PaymentDetailsProvider;
use Vipps\Payment\Gateway\Exception\VippsException;
use Vipps\Payment\Gateway\Transaction\TransactionBuilder;
use Vipps\Payment\Model\Order\PartialVoid\SendMail;
use Vipps\Payment\Model\Order\PartialVoid\Config as PartialVoidConfig;

/**
 * Class SendOfflineVoidEmail
 * @package Vipps\Payment\Observer
 */
class SendOfflineVoidEmail implements ObserverInterface
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
     * @var SendMail
     */
    private $sendMail;

    /**
     * @var PartialVoidConfig
     */
    private $config;

    /**
     * SendOfflineVoidEmail constructor.
     *
     * @param PaymentDataObjectFactoryInterface $dataObjectFactory
     * @param PaymentDetailsProvider $paymentDetailsProvider
     * @param TransactionBuilder $transactionBuilder
     * @param SendMail $sendMail
     * @param PartialVoidConfig $config
     */
    public function __construct(
        PaymentDataObjectFactoryInterface $dataObjectFactory,
        PaymentDetailsProvider $paymentDetailsProvider,
        TransactionBuilder $transactionBuilder,
        SendMail $sendMail,
        PartialVoidConfig $config
    ) {
        $this->dataObjectFactory = $dataObjectFactory;
        $this->paymentDetailsProvider = $paymentDetailsProvider;
        $this->transactionBuilder = $transactionBuilder;
        $this->sendMail = $sendMail;
        $this->config = $config;
    }

    /**
     * Send email to customer about offline void
     *
     * @param Observer $observer
     *
     * @throws LocalizedException
     * @throws MailException
     * @throws VippsException
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getData('order');
        $payment = $order->getPayment();
        $offlineVoidEnabled = $this->config->isOfflinePartialVoidEnabled($order->getStoreId());
        $sendMailEnabled = $this->config->isSendMailEnabled($order->getStoreId());

        if ($payment->getMethod() === 'vipps' && $offlineVoidEnabled && $sendMailEnabled) {
            $paymentDataObject = $this->dataObjectFactory->create($payment);
            $response = $this->paymentDetailsProvider->get(['payment' => $paymentDataObject]);
            $transaction = $this->transactionBuilder->setData($response)->build();
            if ($transaction->getTransactionSummary()->getRemainingAmountToCapture() > 0) {
                $this->sendMail->send($order);
            }
        }
    }
}
