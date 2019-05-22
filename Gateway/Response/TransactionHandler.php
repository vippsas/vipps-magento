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
namespace Vipps\Payment\Gateway\Response;

use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\{Payment, Payment\Transaction as PaymentTransaction};
use Vipps\Payment\Gateway\{Request\SubjectReader, Transaction\Transaction, Transaction\TransactionBuilder};

/**
 * Class TransactionHandler
 * @package Vipps\Payment\Gateway\Response
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class TransactionHandler implements HandlerInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var TransactionBuilder
     */
    private $transactionBuilder;

    /**
     * CaptureHandler constructor.
     *
     * @param SubjectReader $subjectReader
     * @param TransactionBuilder $transactionBuilder
     */
    public function __construct(
        SubjectReader $subjectReader,
        TransactionBuilder $transactionBuilder
    ) {
        $this->subjectReader = $subjectReader;
        $this->transactionBuilder = $transactionBuilder;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $handlingSubject
     * @param array $response
     */
    public function handle(array $handlingSubject, array $response) //@codingStandardsIgnoreLine
    {
        $paymentDO = $this->subjectReader->readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();

        $transaction = $this->transactionBuilder
            ->setData($response)
            ->build();

        if ($payment instanceof Payment) {
            $status = $transaction->getTransactionInfo()->getStatus();
            $transactionId = $transaction->getTransactionInfo()->getTransactionId();

            switch ($status) {
                case Transaction::TRANSACTION_STATUS_CANCELLED:
                    $transactionId .= '-void';
                    break;
                case Transaction::TRANSACTION_OPERATION_RESERVE:
                    $payment->setIsTransactionClosed(false);
                    break;
            }

            $payment->setTransactionId($transactionId);
            $payment->setTransactionAdditionalInfo(
                PaymentTransaction::RAW_DETAILS,
                $transaction->getTransactionInfo()->getData() + $transaction->getTransactionSummary()->getData()
            );
        }
    }
}
