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

namespace Vipps\Payment\Test\Unit\GraphQl\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResourceModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\GatewayEpayment\Command\PaymentDetailsProvider;
use Vipps\Payment\GatewayEpayment\Data\Payment;
use Vipps\Payment\GraphQl\ReturnAuthorizer;
use Vipps\Payment\GraphQl\Resolver\ActivateCart;
use Vipps\Payment\Model\ReturnToken;
use Vipps\Payment\Model\Transaction\StatusVisitor;

class ActivateCartTest extends TestCase
{
    private Quote|MockObject $quote;
    private CartRepositoryInterface|MockObject $cartRepository;
    private ReturnToken|MockObject $returnToken;

    protected function setUp(): void
    {
        $this->quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $this->quote->method('getId')->willReturn(42);
        $this->quote->setData('is_active', 0);
        $this->quote->setData('reserved_order_id', '000000042');
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->returnToken = $this->createMock(ReturnToken::class);
    }

    /**
     * Aborted is what the shopper's own cancel in the app produces; expired and terminated are covered by
     * StatusVisitor::isRestorable(), shared with the return page.
     */
    public function testGivesBackTheCartOfAPaymentTheShopperCancelled(): void
    {
        $this->returnToken->method('consume')->willReturn(true);
        $this->cartRepository->expects($this->once())->method('save')->with($this->quote);

        $this->assertSame('masked-42', $this->resolve(Payment::STATE_ABORTED));
        $this->assertTrue((bool)$this->quote->getIsActive());
        $this->assertNull($this->quote->getReservedOrderId());
    }

    /**
     * Reactivating the cart of a live payment would let it be changed before the order is placed from it.
     */
    public function testLeavesTheCartOfALivePaymentAlone(): void
    {
        $this->returnToken->expects($this->never())->method('consume');
        $this->cartRepository->expects($this->never())->method('save');

        $this->assertNull($this->resolve(Payment::STATE_AUTHORIZED));
    }

    public function testOnlyOneOfTwoSimultaneousCallsGetsTheCart(): void
    {
        $this->returnToken->method('consume')->willReturn(false);
        $this->cartRepository->expects($this->never())->method('save');

        $this->assertNull($this->resolve(Payment::STATE_ABORTED));
    }

    private function resolve(string $state): ?string
    {
        $authorizer = $this->createMock(ReturnAuthorizer::class);
        $authorizer->method('authorize')->willReturn([$this->createMock(QuoteInterface::class), $this->quote]);

        $detailsProvider = $this->createMock(PaymentDetailsProvider::class);
        $detailsProvider->method('get')->willReturn(
            new Payment([Payment::STATE => $state, Payment::REFERENCE => '000000042'])
        );

        $config = $this->createMock(ConfigInterface::class);
        $config->method('getValue')->willReturn(true);

        $mask = $this->getMockBuilder(QuoteIdMask::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $mask->setData('masked_id', 'masked-42');
        $maskFactory = $this->createMock(QuoteIdMaskFactory::class);
        $maskFactory->method('create')->willReturn($mask);

        return (new ActivateCart(
            $maskFactory,
            $this->createMock(QuoteIdMaskResourceModel::class),
            $this->cartRepository,
            $authorizer,
            $this->returnToken,
            $detailsProvider,
            $config,
            new StatusVisitor()
        ))->resolve(
            $this->createMock(Field::class),
            $this->createMock(ContextInterface::class),
            $this->createMock(ResolveInfo::class),
            null,
            ['order_number' => '000000042', 'token' => 'token']
        );
    }
}
