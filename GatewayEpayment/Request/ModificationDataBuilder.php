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
namespace Vipps\Payment\GatewayEpayment\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\InfoInterface;

/**
 * Class ModificationDataBuilder
 * @package Vipps\Payment\GatewayEpayment\Request
 */
class ModificationDataBuilder implements BuilderInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * LogisticsDataBuilder constructor.
     *
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        SubjectReader $subjectReader
    ) {
        $this->subjectReader = $subjectReader;
    }

    /**
     * Default data builder
     *
     * @param array $buildSubject
     *
     * @return array
     * @throws \Exception
     */
    public function build(array $buildSubject)
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $amount = $this->subjectReader->readAmount($buildSubject);

        /** @var InfoInterface $payment */
        $payment = $paymentDO->getPayment();
        $order = $paymentDO->getOrder();

        $result = [];
        if ($amount > 0) {
            $result['modificationAmount'] = [
                'currency' => $order->getCurrencyCode(),
                'value' => $amount * 100
            ];
        }

        $result['modificationReference'] = $order->getOrderIncrementId();

        return $result;
    }
}
