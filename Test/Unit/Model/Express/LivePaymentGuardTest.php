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

namespace Vipps\Payment\Test\Unit\Model\Express;

use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\GatewayEpayment\Data\Payment;
use Vipps\Payment\Model\Express\LivePaymentGuard;
use Vipps\Payment\Model\LockManager;
use Vipps\Payment\Model\OrderLocator;
use Vipps\Payment\Model\PaymentProvider;
use Vipps\Payment\Model\Quote\CancelFacade;
use Vipps\Payment\Model\QuoteRepository;
use Vipps\Payment\Model\TransactionProcessor;

/**
 * Starting another Express payment terminates an unapproved one; an approved one is never terminated
 * but becomes the order instead.
 *
 * @package Vipps\Payment\Test\Unit\Model\Express
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class LivePaymentGuardTest extends TestCase
{
    private const CART_ID = 42;

    /** @var array<string, string|\Throwable> payment state, or the error reading it throws, per reference */
    private array $states = [];

    /** @var QuoteInterface[] */
    private array $liveRows = [];

    /** @var string[] */
    private array $calls = [];

    private CancelFacade|MockObject $cancelFacade;
    private TransactionProcessor|MockObject $transactionProcessor;
    private OrderLocator|MockObject $orderLocator;
    private LockManager|MockObject $lockManager;

    protected function setUp(): void
    {
        $this->cancelFacade = $this->createMock(CancelFacade::class);
        $this->cancelFacade->expects($this->never())->method('cancel');

        $this->transactionProcessor = $this->createMock(TransactionProcessor::class);
        $this->transactionProcessor->method('process')->willReturnCallback(function () {
            $this->calls[] = 'process';
            return null;
        });

        $this->orderLocator = $this->createMock(OrderLocator::class);

        $this->lockManager = $this->createMock(LockManager::class);
        $this->lockManager->method('lock')->willReturn(true);
        $this->lockManager->method('unlock')->willReturnCallback(function () {
            $this->calls[] = 'unlock';
            return true;
        });
    }

    public function testTerminatesAnUnapprovedPaymentAndCreatesTheNewOne(): void
    {
        $this->row('000000001', Payment::STATE_CREATED);
        $this->cancelFacade->expects($this->once())->method('terminateIfCreated')->willReturn(true);

        $decision = $this->guard()->initiate(self::CART_ID, $this->createPayment());

        $this->assertTrue($decision->isProceed());
        $this->assertSame(['redirectUrl' => 'https://vipps/new'], $decision->getPaymentResponse());
    }

    public function testNeverTerminatesAnApprovedPaymentAndPlacesItsOrderInstead(): void
    {
        $this->row('000000001', Payment::STATE_AUTHORIZED);
        $order = $this->createMock(OrderInterface::class);
        $this->orderLocator->method('get')->with('000000001')->willReturn($order);

        $this->cancelFacade->expects($this->never())->method('terminateIfCreated');
        $this->transactionProcessor->expects($this->once())->method('process');

        $decision = $this->guard()->initiate(self::CART_ID, $this->createPaymentNever());

        $this->assertTrue($decision->isOrderPlaced());
        $this->assertSame($order, $decision->getOrder());
    }

    public function testAnApprovedPaymentThatCannotBePlacedYetStillBlocksANewOne(): void
    {
        $this->row('000000001', Payment::STATE_AUTHORIZED);
        $this->orderLocator->method('get')->willReturn(null);
        $this->orderLocator->method('getByQuoteId')->willReturn(null);

        $this->cancelFacade->expects($this->never())->method('terminateIfCreated');

        $decision = $this->guard()->initiate(self::CART_ID, $this->createPaymentNever());

        $this->assertTrue($decision->isAuthorisedPending());
    }

    public function testAPaymentApprovedWhileBeingTerminatedIsCompletedNotReplaced(): void
    {
        $this->row('000000001', Payment::STATE_CREATED);
        // Vipps/MobilePay refuses to terminate it because the shopper approved it in the meantime.
        $this->cancelFacade->method('terminateIfCreated')->willReturnCallback(function () {
            $this->states['000000001'] = Payment::STATE_AUTHORIZED;
            return false;
        });
        $this->orderLocator->method('get')->willReturn($this->createMock(OrderInterface::class));

        $decision = $this->guard()->initiate(self::CART_ID, $this->createPaymentNever());

        $this->assertTrue($decision->isOrderPlaced());
    }

    public function testTerminatesTheUnapprovedAndCompletesTheApprovedWhenTheCartHasBoth(): void
    {
        $this->row('000000002', Payment::STATE_AUTHORIZED);
        $this->row('000000001', Payment::STATE_CREATED);
        $this->orderLocator->method('get')->willReturn($this->createMock(OrderInterface::class));

        $this->cancelFacade->expects($this->once())->method('terminateIfCreated')
            ->with($this->callback(fn (QuoteInterface $row) => $row->getReservedOrderId() === '000000001'))
            ->willReturn(true);

        $decision = $this->guard()->initiate(self::CART_ID, $this->createPaymentNever());

        $this->assertTrue($decision->isOrderPlaced());
    }

    public function testAnUnreadablePaymentNeverBlocksANewOne(): void
    {
        $this->row('000000001', new \RuntimeException('Vipps/MobilePay unavailable'));
        $this->cancelFacade->expects($this->never())->method('terminateIfCreated');

        $this->assertTrue($this->guard()->initiate(self::CART_ID, $this->createPayment())->isProceed());
    }

    public function testAFailedTerminationNeverBlocksANewOne(): void
    {
        $this->row('000000001', Payment::STATE_CREATED);
        $this->cancelFacade->method('terminateIfCreated')->willReturn(false);

        $this->assertTrue($this->guard()->initiate(self::CART_ID, $this->createPayment())->isProceed());
    }

    /**
     * @dataProvider finishedStateProvider
     */
    public function testLeavesFinishedPaymentsToTheOrderFetch(string $state): void
    {
        $this->row('000000001', $state);
        $this->cancelFacade->expects($this->never())->method('terminateIfCreated');
        $this->transactionProcessor->expects($this->never())->method('process');

        $this->assertTrue($this->guard()->initiate(self::CART_ID, $this->createPayment())->isProceed());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function finishedStateProvider(): array
    {
        return [
            'aborted' => [Payment::STATE_ABORTED],
            'expired' => [Payment::STATE_EXPIRED],
            'terminated' => [Payment::STATE_TERMINATED],
        ];
    }

    /**
     * TransactionProcessor takes its own lock, and LockManager holds one at a time, so placing the order
     * while still holding the cart lock would fail and the approved payment would never become an order.
     */
    public function testReleasesTheCartLockBeforePlacingTheOrder(): void
    {
        $this->row('000000001', Payment::STATE_AUTHORIZED);
        $this->orderLocator->method('get')->willReturn($this->createMock(OrderInterface::class));

        $this->guard()->initiate(self::CART_ID, $this->createPaymentNever());

        $this->assertSame(['unlock', 'process'], $this->calls);
    }

    public function testALockThatCannotBeHadNeverBlocksANewPayment(): void
    {
        $this->lockManager = $this->createMock(LockManager::class);
        $this->lockManager->method('lock')->willReturn(false);
        $this->lockManager->expects($this->never())->method('unlock');

        $this->assertTrue($this->guard()->initiate(self::CART_ID, $this->createPayment())->isProceed());
    }

    public function testACartWithoutAnIdIsNotLookedUp(): void
    {
        $repository = $this->repository();
        $repository->expects($this->never())->method('getLiveByQuote');

        $this->assertTrue($this->guard($repository)->initiate(null, $this->createPayment())->isProceed());
    }

    public function testOnReturnAnUnapprovedPaymentIsLeftAlone(): void
    {
        $this->row('000000001', Payment::STATE_CREATED);
        $this->cancelFacade->expects($this->never())->method('terminateIfCreated');
        $this->transactionProcessor->expects($this->never())->method('process');

        $this->assertNull($this->guard()->placeAuthorised(self::CART_ID));
    }

    public function testOnReturnAnApprovedPaymentBecomesTheOrder(): void
    {
        $this->row('000000001', Payment::STATE_AUTHORIZED);
        $this->orderLocator->method('get')->willReturn($this->createMock(OrderInterface::class));
        $this->cancelFacade->expects($this->never())->method('terminateIfCreated');

        $this->assertTrue($this->guard()->placeAuthorised(self::CART_ID)->isOrderPlaced());
    }

    private function row(string $reference, string|\Throwable $state): void
    {
        $this->states[$reference] = $state;

        $row = $this->createMock(QuoteInterface::class);
        $row->method('getReservedOrderId')->willReturn($reference);
        $row->method('getQuoteId')->willReturn(self::CART_ID);
        $this->liveRows[] = $row;
    }

    private function repository(): QuoteRepository|MockObject
    {
        $repository = $this->createMock(QuoteRepository::class);
        $repository->method('getLiveByQuote')->willReturnCallback(fn () => $this->liveRows);

        return $repository;
    }

    private function guard(?QuoteRepository $repository = null): LivePaymentGuard
    {
        $paymentProvider = $this->createMock(PaymentProvider::class);
        $paymentProvider->method('get')->willReturnCallback(function (string $reference) {
            $state = $this->states[$reference];
            if ($state instanceof \Throwable) {
                throw $state;
            }

            return new Payment([Payment::STATE => $state]);
        });

        return new LivePaymentGuard(
            $repository ?? $this->repository(),
            $paymentProvider,
            $this->cancelFacade,
            $this->transactionProcessor,
            $this->orderLocator,
            $this->lockManager,
            $this->createMock(LoggerInterface::class)
        );
    }

    private function createPayment(): callable
    {
        return fn () => ['redirectUrl' => 'https://vipps/new'];
    }

    private function createPaymentNever(): callable
    {
        return function () {
            $this->fail('A new payment was created while the cart already had an approved one.');
        };
    }
}
