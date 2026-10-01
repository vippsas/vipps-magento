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

namespace Vipps\Payment\Test\Unit\GatewayEpayment\Command;

use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use PHPUnit\Framework\TestCase;
use Vipps\Payment\GatewayEpayment\Command\CancelCommand;
use Vipps\Payment\GatewayEpayment\Data\Payment;
use Vipps\Payment\GatewayEpayment\Request\SubjectReader;
use Vipps\Payment\Model\PaymentProvider;

/**
 * The command reads the payment before it builds a request, so a wrong reference here fails the
 * call before any builder runs.
 *
 * @package Vipps\Payment\Test\Unit\GatewayEpayment\Command
 */
class CancelCommandReferenceTest extends TestCase
{
    /**
     * The regression: a payment with no order keeps its reference on the cart, which placeOrder()
     * clears, so reading it from the order adapter asked Vipps about a null reference.
     */
    public function testUsesTheReferenceTheCallerSupplied(): void
    {
        $this->assertLooksUpPayment('000000001', ['reference' => '000000001'], null);
    }

    public function testFallsBackToTheOrderReference(): void
    {
        $this->assertLooksUpPayment('000000002', [], '000000002');
    }

    /**
     * @param string $expectedReference the reference the payment lookup must receive
     * @param array<string, mixed> $commandSubject what the caller passed in
     * @param string|null $orderIncrementId what the order adapter reports
     */
    private function assertLooksUpPayment(
        string $expectedReference,
        array $commandSubject,
        ?string $orderIncrementId
    ): void {
        $orderAdapter = $this->createMock(OrderAdapterInterface::class);
        $orderAdapter->method('getOrderIncrementId')->willReturn($orderIncrementId);

        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getOrder')->willReturn($orderAdapter);

        $subjectReader = $this->createMock(SubjectReader::class);
        $subjectReader->method('readPayment')->willReturn($paymentDO);

        // Already terminated, so execute() returns before building any request.
        $payment = $this->createMock(Payment::class);
        $payment->method('isTerminated')->willReturn(true);

        $paymentProvider = $this->createMock(PaymentProvider::class);
        $paymentProvider->expects($this->once())
            ->method('get')
            ->with($expectedReference)
            ->willReturn($payment);

        $command = (new \ReflectionClass(CancelCommand::class))->newInstanceWithoutConstructor();
        foreach (['subjectReader' => $subjectReader, 'paymentProvider' => $paymentProvider] as $name => $value) {
            (new \ReflectionProperty(CancelCommand::class, $name))->setValue($command, $value);
        }

        $this->assertTrue($command->execute($commandSubject));
    }
}
