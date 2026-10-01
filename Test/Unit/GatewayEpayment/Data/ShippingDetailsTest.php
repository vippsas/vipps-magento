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

namespace Vipps\Payment\Test\Unit\GatewayEpayment\Data;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use Vipps\Payment\GatewayEpayment\Data\ShippingDetails;

/**
 * @package Vipps\Payment\Test\Unit\GatewayEpayment\Data
 */
class ShippingDetailsTest extends TestCase
{
    /**
     * Captured verbatim from a live GET /epayment/v1/payments/{reference} response. Note there is
     * no flat postalCode anywhere in it, which is why reading that key left the postal-code
     * fallback in QuoteUpdater unable to fire at all.
     */
    public function testReadsThePostalCodeFromTheNestedAddress(): void
    {
        $shippingDetails = $this->create([
            'address' => [
                'addressLine1' => 'Testveien 1',
                'addressLine2' => '',
                'city' => 'Oslo',
                'country' => 'NO',
                'postCode' => '1234'
            ],
            'shippingCost' => 18625,
            'shippingOptionId' => 'visma_customshipping_postnord',
            'shippingOptionName' => 'PostNord'
        ]);

        $this->assertSame('1234', $shippingDetails->getPostalCode());
    }

    public function testFallsBackToAFlatPostalCode(): void
    {
        $this->assertSame('0150', $this->create(['postalCode' => '0150'])->getPostalCode());
    }

    public function testReturnsNullWhenThereIsNoAddress(): void
    {
        $this->assertNull($this->create(['firstName' => 'Einar'])->getPostalCode());
    }

    private function create(array $data): ShippingDetails
    {
        $objectManager = new ObjectManager($this);

        /** @var ShippingDetails $shippingDetails */
        $shippingDetails = $objectManager->getObject(ShippingDetails::class);
        $shippingDetails->setData($data);

        return $shippingDetails;
    }
}
