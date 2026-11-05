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

namespace Vipps\Payment\Test\Unit\GraphQl;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Api\QuoteRepositoryInterface;
use Vipps\Payment\GraphQl\ReturnAuthorizer;
use Vipps\Payment\Model\ResourceModel\Quote as QuoteResource;
use Vipps\Payment\Model\ReturnToken;

class ReturnAuthorizerTest extends TestCase
{
    private const TOKEN = 'a3f1c2d4e5b6a7980a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f6071';

    public function testAuthorisesTheTokenHolderForAGuestCart(): void
    {
        [$vippsQuote, $quote] = $this->createAuthorizer(null)->authorize('000000042', self::TOKEN, $this->context(0));

        $this->assertSame('000000042', $vippsQuote->getReservedOrderId());
        $this->assertSame(42, $quote->getId());
    }

    public function testRejectsTheOrderNumberWithoutItsToken(): void
    {
        $this->expectException(GraphQlAuthorizationException::class);

        $this->createAuthorizer(null)->authorize('000000042', strrev(self::TOKEN), $this->context(0));
    }

    public function testAnUnknownOrderNumberFailsLikeAWrongToken(): void
    {
        $this->expectException(GraphQlAuthorizationException::class);
        $this->expectExceptionMessage('The current user cannot access this payment.');

        $this->createAuthorizer(null)->authorize('000000043', self::TOKEN, $this->context(0));
    }

    public function testRejectsAnotherCustomersCartEvenWithTheToken(): void
    {
        $this->expectException(GraphQlAuthorizationException::class);

        $this->createAuthorizer(7)->authorize('000000042', self::TOKEN, $this->context(8));
    }

    public function testRejectsACustomersCartForAGuest(): void
    {
        $this->expectException(GraphQlAuthorizationException::class);

        $this->createAuthorizer(7)->authorize('000000042', self::TOKEN, $this->context(0));
    }

    public function testAuthorisesTheCustomerWhoOwnsTheCart(): void
    {
        [, $quote] = $this->createAuthorizer(7)->authorize('000000042', self::TOKEN, $this->context(7));

        $this->assertSame(7, $quote->getCustomerId());
    }

    private function createAuthorizer(?int $customerId): ReturnAuthorizer
    {
        $vippsQuote = $this->createMock(QuoteInterface::class);
        $vippsQuote->method('getQuoteId')->willReturn(42);
        $vippsQuote->method('getReservedOrderId')->willReturn('000000042');
        $vippsQuote->method('getReturnToken')->willReturn(self::TOKEN);
        $vippsQuote->method('getCreatedAt')->willReturn(gmdate('Y-m-d H:i:s', time() - 60));

        $vippsQuoteRepository = $this->createMock(QuoteRepositoryInterface::class);
        $vippsQuoteRepository->method('loadByOrderId')->willReturnCallback(
            function ($orderId) use ($vippsQuote) {
                if ($orderId !== '000000042') {
                    throw NoSuchEntityException::singleField('reserved_order_id', $orderId);
                }
                return $vippsQuote;
            }
        );

        // getCustomerId() is a magic getter, so it is backed by real data rather than mocked.
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $quote->method('getId')->willReturn(42);
        $quote->setData('customer_id', $customerId);
        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->method('get')->with(42)->willReturn($quote);

        return new ReturnAuthorizer(
            $vippsQuoteRepository,
            $cartRepository,
            new ReturnToken($this->createMock(QuoteResource::class))
        );
    }

    private function context(int $userId): ContextInterface
    {
        $context = $this->createMock(ContextInterface::class);
        $context->method('getUserId')->willReturn($userId);

        return $context;
    }
}
