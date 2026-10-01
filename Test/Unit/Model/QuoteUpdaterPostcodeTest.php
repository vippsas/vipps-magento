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

namespace Vipps\Payment\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Tax\Model\Config as TaxConfig;
use PHPUnit\Framework\TestCase;
use Vipps\Payment\GatewayEpayment\Data\Payment;
use Vipps\Payment\GatewayEpayment\Data\ShippingDetails;
use Vipps\Payment\GatewayEpayment\Data\UserDetails;
use Vipps\Payment\Model\QuoteUpdater;

/**
 * Covers which postal codes count as "the address still needs one from Vipps/MobilePay".
 *
 * @package Vipps\Payment\Test\Unit\Model
 */
class QuoteUpdaterPostcodeTest extends TestCase
{
    /**
     * @dataProvider postcodeProvider
     */
    public function testRecognisesWhenAPostcodeIsStillMissing(
        ?string $postcode,
        string $configuredDefault,
        bool $expectedUnset
    ): void {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->with(TaxConfig::CONFIG_XML_PATH_DEFAULT_POSTCODE, $this->anything(), $this->anything())
            ->willReturn($configuredDefault);

        $objectManager = new ObjectManager($this);
        /** @var QuoteUpdater $quoteUpdater */
        $quoteUpdater = $objectManager->getObject(QuoteUpdater::class, ['scopeConfig' => $scopeConfig]);

        $quote = $this->createMock(Quote::class);
        $quote->method('getStoreId')->willReturn(1);

        $method = new \ReflectionMethod(QuoteUpdater::class, 'isPostcodeUnset');

        $this->assertSame($expectedUnset, $method->invoke($quoteUpdater, $postcode, $quote));
    }

    /**
     * @return array<string, array{0: ?string, 1: string, 2: bool}>
     */
    public static function postcodeProvider(): array
    {
        return [
            'a real postcode is set' => ['0150', '*', false],
            'null is unset' => [null, '*', true],
            'empty string is unset' => ['', '*', true],
            // The regression: the tax module's placeholder is truthy and used to survive onto orders.
            'the tax default placeholder is unset' => ['*', '*', true],
            'a postcode equal to a non-placeholder default is still unset' => ['0150', '0150', true],
            'placeholder is not special when it is not the configured default' => ['*', '0150', false],
        ];
    }

    /**
     * The guard and the getter only matter if the address-update path actually uses them, so this
     * exercises the wiring: a placeholder postcode must be replaced with the one from Vipps.
     *
     * @dataProvider wiringProvider
     */
    public function testFillsInAPlaceholderPostcodeFromTheApi(
        ?string $currentPostcode,
        ?string $vippsPostcode,
        ?string $expectedSet
    ): void {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('*');

        $objectManager = new ObjectManager($this);
        /** @var QuoteUpdater $quoteUpdater */
        $quoteUpdater = $objectManager->getObject(QuoteUpdater::class, ['scopeConfig' => $scopeConfig]);

        $address = $this->createMock(Address::class);
        $address->method('getPostcode')->willReturn($currentPostcode);

        if ($expectedSet === null) {
            $address->expects($this->never())->method('setPostcode');
        } else {
            $address->expects($this->once())->method('setPostcode')->with($expectedSet);
        }

        $quote = $this->createMock(Quote::class);
        $quote->method('getStoreId')->willReturn(1);
        $quote->method('getShippingAddress')->willReturn($address);

        $method = new \ReflectionMethod(QuoteUpdater::class, 'updateShippingAddress');
        $method->invoke($quoteUpdater, $quote, $this->transaction($vippsPostcode));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: ?string}>
     */
    public static function wiringProvider(): array
    {
        return [
            'placeholder is replaced' => ['*', '0150', '0150'],
            'missing is filled in' => [null, '0150', '0150'],
            'a real postcode is left alone' => ['0101', '0150', null],
            'nothing to fill in with' => ['*', null, null],
        ];
    }

    /**
     * A payment carrying the address shape the API actually returns.
     */
    private function transaction(?string $postCode): Payment
    {
        $objectManager = new ObjectManager($this);

        /** @var ShippingDetails $shippingDetails */
        $shippingDetails = $objectManager->getObject(ShippingDetails::class);
        $shippingDetails->setData($postCode === null ? [] : ['address' => ['postCode' => $postCode]]);

        $userDetails = $this->createMock(UserDetails::class);

        $transaction = $this->createMock(Payment::class);
        $transaction->method('getShippingDetails')->willReturn($shippingDetails);
        $transaction->method('getUserDetails')->willReturn($userDetails);

        return $transaction;
    }
}
