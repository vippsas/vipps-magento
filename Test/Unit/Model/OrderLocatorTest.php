<?php
/**
 * Copyright 2026 Vipps
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

namespace Vipps\Payment\Test\Unit\Model;

use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Vipps\Payment\Model\OrderLocator;

class OrderLocatorTest extends TestCase
{
    /**
     * The shopper cancels a regular payment, whose order is then cancelled, gets the cart back and pays again.
     * The cancelled order must not count as the cart's order, or the new payment can never be placed.
     */
    public function testACancelledOrderIsNotTheCartsActiveOrder(): void
    {
        $filters = [];
        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->method('addFilter')->willReturnCallback(
            function ($field, $value, $condition) use (&$filters, $searchCriteriaBuilder) {
                $filters[] = [$field, $value, $condition];
                return $searchCriteriaBuilder;
            }
        );
        $searchCriteriaBuilder->method('create')->willReturn($this->createMock(SearchCriteria::class));

        $order = $this->createMock(OrderInterface::class);
        $result = $this->createMock(OrderSearchResultInterface::class);
        $result->method('getItems')->willReturn([$order]);
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->method('getList')->willReturn($result);

        $found = (new OrderLocator($searchCriteriaBuilder, $orderRepository))->getActiveByQuoteId(1234);

        $this->assertSame($order, $found);
        $this->assertSame([['quote_id', 1234, 'eq'], ['state', 'canceled', 'neq']], $filters);
    }
}
