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

namespace Vipps\Payment\Test\Unit\GatewayEpayment\Data;

use PHPUnit\Framework\TestCase;
use Vipps\Payment\GatewayEpayment\Data\AggregateFactory;
use Vipps\Payment\GatewayEpayment\Data\Amount;
use Vipps\Payment\GatewayEpayment\Data\AmountFactory;
use Vipps\Payment\GatewayEpayment\Data\Customer;
use Vipps\Payment\GatewayEpayment\Data\CustomerFactory;
use Vipps\Payment\GatewayEpayment\Data\Payment;
use Vipps\Payment\GatewayEpayment\Data\PaymentBuilder;
use Vipps\Payment\GatewayEpayment\Data\PaymentFactory;
use Vipps\Payment\GatewayEpayment\Data\PaymentMethod;
use Vipps\Payment\GatewayEpayment\Data\PaymentMethodFactory;
use Vipps\Payment\GatewayEpayment\Data\Profile;
use Vipps\Payment\GatewayEpayment\Data\ProfileFactory;
use Vipps\Payment\GatewayEpayment\Data\ShippingDetails;
use Vipps\Payment\GatewayEpayment\Data\ShippingDetailsFactory;
use Vipps\Payment\GatewayEpayment\Data\UserDetails;
use Vipps\Payment\GatewayEpayment\Data\UserDetailsFactory;

/**
 * An Express payment the shopper has not approved yet carries no user or shipping details. It still has to
 * be readable: replacing it, cancelling it and the order fetch all start by reading its state.
 *
 * @package Vipps\Payment\Test\Unit\GatewayEpayment\Data
 */
class PaymentBuilderTest extends TestCase
{
    public function testReadsAnUnapprovedExpressPayment(): void
    {
        $payment = $this->build([
            'state' => 'CREATED',
            'reference' => '000450189',
            'amount' => ['currency' => 'NOK', 'value' => 10000],
            'aggregate' => [],
        ]);

        $this->assertTrue($payment->isCreated());
        $this->assertNull($payment->getUserDetails());
        $this->assertNull($payment->getShippingDetails());
        $this->assertFalse($payment->isExpressCheckout());
    }

    public function testBuildsUserAndShippingDetailsOnceApproved(): void
    {
        $payment = $this->build([
            'state' => 'AUTHORIZED',
            'userDetails' => ['email' => 'shopper@example.com'],
            'shippingDetails' => ['postalCode' => '0150'],
        ]);

        $this->assertInstanceOf(UserDetails::class, $payment->getUserDetails());
        $this->assertInstanceOf(ShippingDetails::class, $payment->getShippingDetails());
        $this->assertTrue($payment->isExpressCheckout());
    }

    private function build(array $response): Payment
    {
        $paymentFactory = $this->createMock(PaymentFactory::class);
        $paymentFactory->method('create')->willReturnCallback(fn ($args) => new Payment($args['data']));

        return (new PaymentBuilder(
            $paymentFactory,
            $this->createMock(AggregateFactory::class),
            $this->factory(AmountFactory::class, Amount::class),
            $this->factory(CustomerFactory::class, Customer::class),
            $this->factory(PaymentMethodFactory::class, PaymentMethod::class),
            $this->factory(ProfileFactory::class, Profile::class),
            $this->factory(UserDetailsFactory::class, UserDetails::class),
            $this->factory(ShippingDetailsFactory::class, ShippingDetails::class)
        ))->setData($response)->build();
    }

    /**
     * Like the generated factory: a DataObject from ['data' => ...], which must be an array.
     */
    private function factory(string $factoryClass, string $objectClass): object
    {
        $factory = $this->createMock($factoryClass);
        $factory->method('create')->willReturnCallback(fn (array $args) => new $objectClass($args['data']));

        return $factory;
    }
}
