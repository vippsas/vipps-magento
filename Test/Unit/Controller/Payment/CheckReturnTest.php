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

namespace Vipps\Payment\Test\Unit\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Phrase;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Controller\Payment\CheckReturn;
use Vipps\Payment\Model\Checkout\LastOrderSession;
use Vipps\Payment\Model\Express\CartRestoreCookie;
use Vipps\Payment\Model\Express\LivePaymentDecision;
use Vipps\Payment\Model\Express\LivePaymentGuard;
use Vipps\Payment\Model\Express\LivePaymentMessages;

/**
 * A shopper who approved in the app and came back to the store gets their order now, not at the next
 * order fetch. Anything short of that leaves the restored cart exactly as it was.
 *
 * @package Vipps\Payment\Test\Unit\Controller\Payment
 */
class CheckReturnTest extends TestCase
{
    private LivePaymentGuard|MockObject $guard;
    private LastOrderSession|MockObject $lastOrderSession;
    private array $data = [];
    private ManagerInterface|MockObject $messageManager;

    protected function setUp(): void
    {
        $this->guard = $this->createMock(LivePaymentGuard::class);
        $this->lastOrderSession = $this->createMock(LastOrderSession::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
    }

    public function testAnApprovedPaymentSendsTheShopperToTheirOrder(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $this->guard->method('placeAuthorised')->with(42)->willReturn(LivePaymentDecision::orderPlaced($order));
        $this->lastOrderSession->expects($this->once())->method('store')->with($order);
        // The restore notice queued just before must not follow the shopper to the confirmation.
        $this->messageManager->expects($this->once())->method('getMessages')->with(true);

        $this->execute();

        $this->assertSame(['orderPlaced' => true, 'redirectUrl' => 'https://store/checkout/onepage/success/'], $this->data);
    }

    public function testAnApprovedPaymentNotPlacedYetIsReportedAsPending(): void
    {
        $this->guard->method('placeAuthorised')->willReturn(LivePaymentDecision::authorisedPending());
        $this->lastOrderSession->expects($this->never())->method('store');

        $this->execute();

        $this->assertSame(['orderPlaced' => false, 'pending' => true], $this->data);
    }

    public function testNoApprovedPaymentLeavesTheCartAlone(): void
    {
        $this->guard->method('placeAuthorised')->willReturn(null);
        $this->lastOrderSession->expects($this->never())->method('store');

        $this->execute();

        $this->assertSame(['orderPlaced' => false], $this->data);
    }

    public function testAFailureIsHarmless(): void
    {
        $this->guard->method('placeAuthorised')->willThrowException(new \RuntimeException('Vipps unavailable'));

        $this->execute();

        $this->assertSame(['orderPlaced' => false], $this->data);
    }

    public function testNoCartNoLookup(): void
    {
        $this->guard->expects($this->never())->method('placeAuthorised');

        $this->execute(0);

        $this->assertSame(['orderPlaced' => false], $this->data);
    }

    private function execute(int $quoteId = 42): void
    {
        $result = $this->createMock(JsonResult::class);
        $result->method('setData')->willReturnCallback(function ($data) use ($result) {
            $this->data = $data;
            return $result;
        });
        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->method('create')->willReturn($result);

        $session = $this->createMock(Session::class);
        $session->method('getQuoteId')->willReturn($quoteId);

        $messages = $this->createMock(LivePaymentMessages::class);
        $messages->method('orderPlaced')->willReturn(new Phrase('placed'));
        $messages->method('authorisedPending')->willReturn(new Phrase('pending'));

        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturnCallback(fn ($path) => 'https://store/' . $path . '/');

        (new CheckReturn(
            $resultFactory,
            $session,
            $this->guard,
            $this->lastOrderSession,
            $messages,
            $this->createMock(CartRestoreCookie::class),
            $this->messageManager,
            $urlBuilder,
            $this->createMock(LoggerInterface::class)
        ))->execute();
    }
}
