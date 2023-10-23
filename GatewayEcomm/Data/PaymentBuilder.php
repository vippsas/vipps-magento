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
namespace Vipps\Payment\GatewayEcomm\Data;

/**
 * Class InitSessionBuilder
 * @package Vipps\Payment\GatewayEcomm\Data
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PaymentBuilder
{
    /**
     * @var array
     */
    private $response;
    private PaymentFactory $paymentFactory;
    private AggregateFactory $aggregateFactory;
    private AmountFactory $amountFactory;
    private CustomerFactory $customerFactory;
    private PaymentMethodFactory $paymentMethodFactory;
    private ProfileFactory $profileFactory;

    public function __construct(
        PaymentFactory $paymentFactory,
        AggregateFactory $aggregateFactory,
        AmountFactory $amountFactory,
        CustomerFactory $customerFactory,
        PaymentMethodFactory $paymentMethodFactory,
        ProfileFactory $profileFactory
    ) {
        $this->paymentFactory = $paymentFactory;
        $this->aggregateFactory = $aggregateFactory;
        $this->amountFactory = $amountFactory;
        $this->customerFactory = $customerFactory;
        $this->paymentMethodFactory = $paymentMethodFactory;
        $this->profileFactory = $profileFactory;
    }

    /**
     * Set request to builder
     *
     * @param array $response
     *
     * @return $this
     */
    public function setData(array $response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * build session object
     *
     * @return Payment
     */
    public function build()
    {
        return $this->paymentFactory->create([
            'data' => array_merge(
                $this->response,
                [
                    'aggregate' =>
                        $this->aggregateFactory->create((array)($this->response['aggregate'] ?? null)),
                    'amount' => $this->amountFactory->create((array)($this->response['amount'] ?? null)),
                    'customer' => $this->customerFactory->create((array)($this->response['customer'] ?? null)),
                    'paymentMethod' =>
                        $this->paymentMethodFactory->create((array)($this->response['paymentMethod'] ?? null)),
                    'profile' => $this->profileFactory->create((array)($this->response['profile'] ?? null)),
                    'raw_data' => \json_encode($this->response)
                ]
            )
        ]);
    }
}
