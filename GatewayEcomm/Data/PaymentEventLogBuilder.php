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

use Vipps\Payment\GatewayEcomm\Data\PaymentEventLog\ItemFactory;

/**
 * Class PaymentEventLogBuilder
 * @package Vipps\Payment\GatewayEcomm\Data
 */
class PaymentEventLogBuilder
{
    /**
     * @var array
     */
    private $response;
    /**
     * @var ItemFactory
     */
    private ItemFactory $itemFactory;
    /**
     * @var PaymentEventLogFactory
     */
    private PaymentEventLogFactory $paymentEventLogFactory;
    /**
     * @var AmountFactory
     */
    private $amountFactory;

    /**
     * PaymentEventLogBuilder constructor.
     *
     * @param ItemFactory $itemFactory
     * @param PaymentEventLogFactory $paymentEventLogFactory
     * @param AmountFactory $amountFactory
     */
    public function __construct(
        ItemFactory $itemFactory,
        PaymentEventLogFactory $paymentEventLogFactory,
        AmountFactory $amountFactory
    ) {
        $this->itemFactory = $itemFactory;
        $this->paymentEventLogFactory = $paymentEventLogFactory;
        $this->amountFactory = $amountFactory;
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
     * @return PaymentEventLog
     */
    public function build()
    {
        $items = [];
        foreach ($this->response as $itemData) {
            $items[] = $this->itemFactory->create([
                'data' => array_merge(
                    (array)$itemData,
                    [
                        'amount' => $this->amountFactory->create([
                            'data' => (array)($itemData['amount'] ?? null)
                        ])
                    ]
                )
            ]);
        }

        return $this->paymentEventLogFactory->create(['data' => ['items' => $items]]);
    }
}
