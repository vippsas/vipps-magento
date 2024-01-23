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
namespace Vipps\Payment\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Vipps\Payment\Model\OrderLocator;

/**
 * Class GenericDataBuilder
 * @package Vipps\Payment\Gateway\Request
 */
class GenericDataBuilder implements BuilderInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;
    /**
     * @var OrderLocator
     */
    private $orderLocator;

    /**
     * GenericDataBuilder constructor.
     *
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        SubjectReader $subjectReader,
        OrderLocator $orderLocator
    ) {
        $this->subjectReader = $subjectReader;
        $this->orderLocator = $orderLocator;
    }

    /**
     * This builders for passing parameters into TransferFactory object.
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $scopeId = null;
        $incrementId = $buildSubject['orderId'] ?? null;

        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        if ($paymentDO) {
            $scopeId = $paymentDO->getOrder()->getStoreId();
            $incrementId = $paymentDO->getOrder()->getOrderIncrementId();
        }

        if (!$scopeId && $incrementId) {
            $order = $this->orderLocator->get($incrementId);
            if ($order) {
                $scopeId = $order->getStoreId();
            }
        }

        $buildSubject = array_merge(
            $buildSubject,
            [
                'orderId' => $incrementId,
                'scopeId' => $scopeId
            ]
        );

        return $buildSubject;
    }
}
