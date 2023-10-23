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
 * Class SessionBuilder
 * @package Vipps\Payment\GatewayEcomm\Data
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SessionBuilder
{
    /**
     * @var SessionFactory
     */
    private $sessionFactory;

    /**
     * @var PaymentDetailsFactory
     */
    private $paymentDetailsFactory;

    /**
     * @var AmountFactory
     */
    private $amountFactory;

    /**
     * @var BillingDetailsFactory
     */
    private $billingDetailsFactory;

    /**
     * @var ShippingDetailsFactory
     */
    private $shippingDetailsFactory;

    /**
     * @var AggregateFactory
     */
    private $aggregateFactory;

    /**
     * @var array
     */
    private $response;

    /**
     * SessionBuilder constructor.
     *
     * @param SessionFactory $sessionFactory
     * @param PaymentDetailsFactory $paymentDetailsFactory
     * @param AmountFactory $amountFactory
     * @param BillingDetailsFactory $billingDetailsFactory
     * @param ShippingDetailsFactory $shippingDetailsFactory
     * @param AggregateFactory $aggregateFactory
     */
    public function __construct(
        SessionFactory $sessionFactory,
        PaymentDetailsFactory $paymentDetailsFactory,
        AmountFactory $amountFactory,
        BillingDetailsFactory $billingDetailsFactory,
        ShippingDetailsFactory $shippingDetailsFactory,
        AggregateFactory $aggregateFactory
    ) {
        $this->sessionFactory = $sessionFactory;
        $this->paymentDetailsFactory = $paymentDetailsFactory;
        $this->amountFactory = $amountFactory;
        $this->billingDetailsFactory = $billingDetailsFactory;
        $this->shippingDetailsFactory = $shippingDetailsFactory;
        $this->aggregateFactory = $aggregateFactory;
    }

    /**
     * Set request to builder
     *
     * @param $response
     *
     * @return $this
     */
    public function setData($response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * build session object
     *
     * @return Session
     */
    public function build()
    {
        $paymentDetails = $this->paymentDetailsFactory->create(['data' => [
            'amount' => $this->amountFactory->create([
                'data' => (array)($this->response['paymentDetails']['amount'] ?? null)
            ]),
            'state' => $this->response['paymentDetails']['state'] ?? null,
            'aggregate' => $this->aggregateFactory->create([
                'data' => [(array)($this->response['paymentDetails']['aggregate'] ?? null)]
            ])
        ]]);

        return $this->sessionFactory->create(['data' => [
            'sessionId' => $this->response['sessionId'] ?? null,
            'reference' => $this->response['reference'] ?? null,
            'sessionState' => $this->response['sessionState'] ?? null,
            'paymentMethod' => $this->response['paymentMethod'] ?? null,
            'paymentDetails' => $paymentDetails,
            'shippingDetails' => $this->shippingDetailsFactory->create([
                'data' => (array)($this->response['shippingDetails'] ?? null)
            ]),
            'billingDetails' => $this->billingDetailsFactory->create([
                'data' => (array)($this->response['billingDetails'] ?? null)
            ]),
        ]]);
    }
}
