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

namespace Vipps\Payment\Test\Unit\GatewayEpayment\Request;

use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use PHPUnit\Framework\TestCase;
use Vipps\Payment\GatewayEpayment\Request\ModificationDataBuilder;
use Vipps\Payment\GatewayEpayment\Request\SubjectReader;

/**
 * @package Vipps\Payment\Test\Unit\GatewayEpayment\Request
 */
class ModificationDataBuilderTest extends TestCase
{
    public function testTakesTheReferenceFromTheOrder(): void
    {
        $this->assertSame('000000002', $this->build('000000002', [])['modificationReference']);
    }

    /**
     * Without this the body comes out empty for a payment with no order, and an empty PHP array
     * serialises as [] where Vipps requires an object, so the request is rejected.
     */
    public function testPrefersACallerSuppliedReference(): void
    {
        $result = $this->build(null, ['reference' => '000000001']);

        $this->assertSame('000000001', $result['modificationReference']);
    }

    /**
     * @param string|null $orderIncrementId what the order adapter reports
     * @param array<string, mixed> $buildSubject what the caller passed in
     * @return array<string, mixed>
     */
    private function build(?string $orderIncrementId, array $buildSubject): array
    {
        $orderAdapter = $this->createMock(OrderAdapterInterface::class);
        $orderAdapter->method('getOrderIncrementId')->willReturn($orderIncrementId);
        $orderAdapter->method('getCurrencyCode')->willReturn('NOK');

        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getOrder')->willReturn($orderAdapter);

        $subjectReader = $this->createMock(SubjectReader::class);
        $subjectReader->method('readPayment')->willReturn($paymentDO);
        $subjectReader->method('readAmount')->willReturn(0.0);

        return (new ModificationDataBuilder($subjectReader))->build($buildSubject);
    }
}
