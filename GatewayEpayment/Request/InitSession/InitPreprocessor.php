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
namespace Vipps\Payment\GatewayEpayment\Request\InitSession;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Vipps\Payment\GatewayEpayment\Request\SubjectReader;
use Vipps\Payment\Model\Method\Vipps;

class InitPreprocessor implements BuilderInterface
{
    private SubjectReader $subjectReader;

    public function __construct(
        SubjectReader $subjectReader
    ) {
        $this->subjectReader = $subjectReader;
    }

    /**
     * Get merchant related data for Initiate payment request.
     *
     * @throws \Exception
     */
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        if ($payment instanceof QuotePayment) {
            $payment->setMethod(Vipps::METHOD_CODE);

            $quote = $payment->getQuote();
            $quote->setReservedOrderId(null);
            $quote->reserveOrderId();

            $quote->getPayment()
                ->setAdditionalInformation(Vipps::METHOD_TYPE_KEY, Vipps::METHOD_TYPE_EPAYMENT_CHECKOUT);
        }

        return [];
    }
}
