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

use PHPUnit\Framework\TestCase;
use Vipps\Payment\Api\Payment\CommandManagerInterface;
use Vipps\Payment\GatewayEpayment\Data\Payment;
use Vipps\Payment\GatewayEpayment\Data\PaymentBuilder;
use Vipps\Payment\Model\PaymentProvider;

/**
 * A payment is read once per request, but confirming a termination needs the state as it is now.
 *
 * @package Vipps\Payment\Test\Unit\Model
 */
class PaymentProviderTest extends TestCase
{
    public function testReusesTheStateReadEarlierInTheRequest(): void
    {
        $this->assertSame(1, $this->reads(false));
    }

    public function testForceReloadReadsTheStateAgain(): void
    {
        $this->assertSame(2, $this->reads(true));
    }

    private function reads(bool $forceReload): int
    {
        $reads = 0;
        $commandManager = $this->createMock(CommandManagerInterface::class);
        $commandManager->method('getPayment')->willReturnCallback(function () use (&$reads) {
            $reads++;
            return [];
        });

        $paymentBuilder = $this->createMock(PaymentBuilder::class);
        $paymentBuilder->method('setData')->willReturnSelf();
        $paymentBuilder->method('build')->willReturn(new Payment());

        $provider = new PaymentProvider($commandManager, $paymentBuilder);
        $provider->get('000000001');
        $provider->get('000000001', $forceReload);

        return $reads;
    }
}
