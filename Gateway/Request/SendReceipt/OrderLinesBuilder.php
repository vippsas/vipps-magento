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
namespace Vipps\Payment\Gateway\Request\SendReceipt;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Vipps\Payment\Gateway\Request\SubjectReader;

/**
 * Class OrderLinesBuilder
 * @package Vipps\Payment\Gateway\Request
 */
class OrderLinesBuilder implements BuilderInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * GenericDataBuilder constructor.
     *
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        SubjectReader $subjectReader
    ) {
        $this->subjectReader = $subjectReader;
    }

    /**
     * This builders for passing parameters into TransferFactory object.
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        /** @var OrderInterface|Order $order */
        $order = $buildSubject['order'] ?? null;
        if (!$order) {
            return [];
        }

        $orderLines = [];
        foreach ($order->getItemsCollection() as $item) {
            /** @var Order\Item $item */
            if ($item->getChildrenItems()) {
                continue;
            }

            $totalAmount = $item->getRowTotal() + $item->getTaxAmount() - $item->getDiscountAmount();
            $totalAmountExcludingTax = $totalAmount - $item->getTaxAmount();

            $orderLines[] = [
                'name' => $item->getName(),
                'id' => $item->getSku(),
                'totalAmount' => (int)($totalAmount * 100),
                'totalAmountExcludingTax' => (int)($totalAmountExcludingTax * 100),
                'totalTaxAmount' => (int)($item->getTaxAmount() * 100),
                'taxPercentage' => (int)round($item->getTaxAmount() * 100 / $totalAmount),
                'unitInfo' => [
                    'unitPrice' => (int)($item->getPrice() * 100),
                    'quantity' => (string)$item->getQtyOrdered()
                ],
                'discount' => (int)($item->getDiscountAmount() * 100),
                'productUrl' => $this->getProductUrl($item),
                'isReturn' => false,
                'isShipping' => false
            ];
        }

        $orderLines[] = [
            'name' => $order->getShippingDescription(),
            'id' => 'shipping',
            'totalAmount' => (int)($order->getShippingInclTax() * 100),
            'totalAmountExcludingTax' => (int)($order->getShippingAmount() * 100),
            'totalTaxAmount' => (int)($order->getShippingTaxAmount() * 100),
            'taxPercentage' => (int)round($order->getShippingTaxAmount() * 100 / $order->getShippingInclTax()),
            'discount' => (int)($order->getShippingDiscountAmount() * 100),
            'isReturn' => false,
            'isShipping' => true
        ];


        return ['orderLines'=> $orderLines];
    }

    public function getProductUrl($item)
    {
        if ($item->getRedirectUrl()) {
            return $item->getRedirectUrl();
        }

        /** @var Order\Item $item */
        $product = $item->getProduct();
        $option = $item->getOptionByCode('product_type');
        if ($option) {
            $product = $option->getProduct();
        }

        return $product->getUrlModel()->getUrl($product);
    }
}
