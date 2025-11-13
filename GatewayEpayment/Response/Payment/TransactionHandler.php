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
namespace Vipps\Payment\GatewayEpayment\Response\Payment;

use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction as PaymentTransaction;
use Vipps\Payment\GatewayEpayment\Data\PaymentBuilder;
use Vipps\Payment\GatewayEpayment\Request\SubjectReader;
use Vipps\Payment\Gateway\Transaction\Transaction;
use Vipps\Payment\Gateway\Transaction\TransactionBuilder;

/**
 * Class TransactionHandler
 * @package Vipps\Payment\GatewayEpayment\Response
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class TransactionHandler implements HandlerInterface
{

    /**
     * @var string
     */
    const TRANSACTION_STATUS_RESERVE = 'reserve';

    /**
     * @var string
     */
    const TRANSACTION_STATUS_RESERVED = 'reserved';

    /**
     * @var string
     */
    const TRANSACTION_STATUS_CANCELLED = 'cancelled';


    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var PaymentBuilder
     */
    private $paymentBuilder;

    /**
     * CaptureHandler constructor.
     *
     * @param SubjectReader $subjectReader
     * @param PaymentBuilder $paymentBuilder
     */
    public function __construct(
        SubjectReader $subjectReader,
        PaymentBuilder $paymentBuilder
    ) {
        $this->subjectReader = $subjectReader;
        $this->paymentBuilder = $paymentBuilder;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $handlingSubject
     * @param array $response
     */
    public function handle($handlingSubject, $response) //@codingStandardsIgnoreLine
    {
        $paymentDO = $this->subjectReader->readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();

        $transaction = $this->paymentBuilder
            ->setData($response)
            ->build();

        if ($payment instanceof Payment) {

            $transactionSummary = [
                'cancelledAmount'  => $transaction->getAggregate()->getCancelledAmount()->getValue(),
                'capturedAmount'   => $transaction->getAggregate()->getCapturedAmount()->getValue(),
                'refundedAmount'   => $transaction->getAggregate()->getRefundedAmount()->getValue(),
                'authorizedAmount' => $transaction->getAggregate()->getAuthorizedAmount()->getValue(),
                'currency'         => (string) $transaction->getAmount()->getCurrency(),
            ];

            $payment->setTransactionAdditionalInfo(
                PaymentTransaction::RAW_DETAILS,
                $transactionSummary
            );
        }
    }
}
