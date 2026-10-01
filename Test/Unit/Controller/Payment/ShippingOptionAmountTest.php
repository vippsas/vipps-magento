<?php
/*
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

namespace Vipps\Payment\Test\Unit\Controller\Payment;

use Magento\Quote\Api\Data\ShippingMethodInterface;
use PHPUnit\Framework\TestCase;
use Vipps\Payment\Controller\Payment\ShippingDetails;

/**
 * The amount offered to the shopper in the app is also the amount added to the authorisation,
 * so it has to be what the order will actually charge.
 *
 * @package Vipps\Payment\Test\Unit\Controller\Payment
 */
class ShippingOptionAmountTest extends TestCase
{
    /**
     * @dataProvider amountProvider
     */
    public function testSendsTheAmountTheShopperIsCharged(
        float $amount,
        ?float $priceInclTax,
        int $expected
    ): void {
        $rate = $this->createMock(ShippingMethodInterface::class);
        $rate->method('getAmount')->willReturn($amount);
        $rate->method('getPriceInclTax')->willReturn($priceInclTax);

        $this->assertSame($expected, $this->invoke($rate));
    }

    /**
     * @return array<string, array{0: float, 1: ?float, 2: int}>
     */
    public static function amountProvider(): array
    {
        return [
            // The regression: 149 ex tax is 186.25 charged, and the shopper must see 186.25.
            'sends the tax-inclusive price' => [149.00, 186.25, 18625],
            'free shipping stays zero' => [0.0, 0.0, 0],
            'falls back when the carrier sets no tax-inclusive price' => [149.00, null, 14900],
            'falls back when the tax-inclusive price is zero' => [149.00, 0.0, 14900],
            // Float multiplication lands on 18624.999... and truncation would under-charge by a ore.
            'rounds rather than truncates' => [149.00, 186.25, 18625],
        ];
    }

    private function invoke(ShippingMethodInterface $rate): int
    {
        // The method under test reads only its argument, so the controller's many collaborators
        // are irrelevant here and constructing them would only couple this test to them.
        $controller = (new \ReflectionClass(ShippingDetails::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(ShippingDetails::class, 'getShippingOptionAmount');

        return $method->invoke($controller, $rate);
    }
}
