<?php
/**
 * Copyright 2020 Vipps
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
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Api\QuoteRepositoryInterface;
use Vipps\Payment\Controller\Payment\Fallback;
use Vipps\Payment\GatewayEpayment\Config\Config;
use Vipps\Payment\GatewayEpayment\Data\Payment;
use Vipps\Payment\Model\Express\CartRestoreCookie;
use Vipps\Payment\Model\Fallback\AuthoriseProxy;
use Vipps\Payment\Model\Gdpr\Compliance;
use Vipps\Payment\Model\OrderLocator;
use Vipps\Payment\Model\ReturnToken;
use Vipps\Payment\Model\Transaction\StatusVisitor;
use Vipps\Payment\Model\TransactionProcessor;

/**
 * The shopper's browser coming back from Vipps/MobilePay. Fallback asks TransactionProcessor what happened
 * and sends the shopper to their order, back to their cart, or tells them it will be checked again.
 *
 * @package Vipps\Payment\Test\Unit\Controller\Payment
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class FallbackTest extends TestCase
{
    private const REFERENCE = '000450189';

    private TransactionProcessor|MockObject $transactionProcessor;
    private AuthoriseProxy|MockObject $authoriseProxy;
    private OrderLocator|MockObject $orderLocator;
    private CartRepositoryInterface|MockObject $cartRepository;
    private ManagerInterface|MockObject $messageManager;
    private CartRestoreCookie|MockObject $cartRestoreCookie;
    private Session|MockObject $checkoutSession;
    private ReturnToken|MockObject $returnToken;
    private ?string $redirectPath = null;
    private bool $cartPersistence = true;
    private string $fetchDest = 'document';
    private mixed $result = null;
    private ?string $reference = self::REFERENCE;

    protected function setUp(): void
    {
        $this->transactionProcessor = $this->createMock(TransactionProcessor::class);
        $this->authoriseProxy = $this->createMock(AuthoriseProxy::class);
        $this->authoriseProxy->method('getOrderId')->willReturn(self::REFERENCE);
        $this->orderLocator = $this->createMock(OrderLocator::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->cartRestoreCookie = $this->createMock(CartRestoreCookie::class);
        $this->checkoutSession = $this->createMock(Session::class);
        $this->returnToken = $this->createMock(ReturnToken::class);
    }

    public function testAnApprovedPaymentGoesToTheConfirmation(): void
    {
        $this->transactionProcessor->method('process')->willReturn($this->payment(Payment::STATE_AUTHORIZED));
        $this->orderLocator->method('get')->with(self::REFERENCE)->willReturn($this->createMock(OrderInterface::class));

        $this->messageManager->expects($this->once())->method('addWarningMessage')
            ->with($this->callback(fn ($message) => (string)$message === 'Your order was successfully placed.'));
        $this->checkoutSession->expects($this->once())->method('clearStorage');

        $this->execute();

        $this->assertSame('checkout/onepage/success', $this->redirectPath);
    }

    public function testACancelledPaymentHandsTheCartBack(): void
    {
        $this->transactionProcessor->method('process')->willReturn($this->payment(Payment::STATE_TERMINATED));

        $cart = $this->createMock(Quote::class);
        $cart->expects($this->once())->method('setIsActive')->with(true)->willReturnSelf();
        $this->cartRepository->method('get')->willReturn($cart);
        $this->cartRepository->expects($this->once())->method('save')->with($cart);

        $this->execute();

        $this->assertSame('checkout/cart', $this->redirectPath);
    }

    public function testACancelledPaymentLeavesTheCartAloneWithoutCartPersistence(): void
    {
        $this->cartPersistence = false;
        $this->transactionProcessor->method('process')->willReturn($this->payment(Payment::STATE_TERMINATED));
        $this->cartRepository->expects($this->never())->method('save');

        $this->execute();

        $this->assertSame('checkout/cart', $this->redirectPath);
    }

    /**
     * process() logs and swallows its own failures, such as a lock held by the cron, and returns nothing.
     * The payment is still in the queue, so the shopper is told it will be checked again.
     */
    public function testAnUnresolvedPaymentIsLeftForTheOrderFetch(): void
    {
        $this->transactionProcessor->method('process')->willReturn(null);

        $this->messageManager->expects($this->once())->method('addWarningMessage')
            ->with($this->callback(fn ($message) => str_contains((string)$message, 'It will be checked later again')));

        $this->execute();

        $this->assertSame('checkout/cart', $this->redirectPath);
    }

    /**
     * No message is shown: the only people a rejection reaches are real shoppers who have done nothing
     * wrong, and telling them their payment failed when it has not is what makes them pay twice.
     */
    public function testAnUnauthorisedReturnIsRefused(): void
    {
        $this->authoriseProxy->method('do')->willThrowException(new LocalizedException(__('Invalid request')));
        $this->transactionProcessor->expects($this->never())->method('process');
        $this->returnToken->expects($this->never())->method('consume');
        $this->messageManager->expects($this->never())->method('addErrorMessage');

        $this->execute();

        $this->assertSame('checkout/cart', $this->redirectPath);
    }

    /**
     * A return link whose token has already been used is the shopper opening their own confirmation again
     * far more often than it is an attack. The reference is sequential, so the visitor's own session is the
     * only thing that may decide which of the two it is.
     *
     * @dataProvider unusableTokenDataProvider
     */
    public function testAnUnauthorisedReturnDecidesFromTheSessionAlone(
        ?string $reference,
        ?string $lastRealOrderId,
        ?int $lastQuoteId,
        string $expectedPath
    ): void {
        $this->reference = $reference;
        $this->authoriseProxy->method('do')->willThrowException(new LocalizedException(__('Invalid request')));
        $this->checkoutSession->method('getLastRealOrderId')->willReturn($lastRealOrderId);
        $this->checkoutSession->method('getLastQuoteId')->willReturn($lastQuoteId);

        // The success page clears the flag it checks, so asserting the path alone would pass on a shopper
        // who is then bounced straight back to the cart.
        $this->checkoutSession
            ->expects($expectedPath === 'checkout/onepage/success' ? $this->once() : $this->never())
            ->method('setLastSuccessQuoteId')
            ->with($lastQuoteId);

        $this->execute();

        $this->assertSame($expectedPath, $this->redirectPath);
    }

    public function unusableTokenDataProvider(): array
    {
        return [
            'shopper reopening their own used link' =>
                [self::REFERENCE, self::REFERENCE, 42, 'checkout/onepage/success'],
            'someone else, or an expired session' => [self::REFERENCE, '000450190', 42, 'checkout/cart'],
            'no order in the session at all' => [self::REFERENCE, null, null, 'checkout/cart'],
            'order in the session but not its cart' => [self::REFERENCE, self::REFERENCE, null, 'checkout/cart'],
            'no reference on the request' => [null, self::REFERENCE, 42, 'checkout/cart'],
        ];
    }

    /**
     * The order number is sequential. A request that fails authorisation must not put someone else's order in
     * the visitor's session, where the success page shows its email and offers to attach it to a new account,
     * nor hand them the cart.
     */
    public function testAnUnauthorisedReturnNeverReachesTheSession(): void
    {
        $this->authoriseProxy->method('do')->willThrowException(new LocalizedException(__('Invalid request')));
        $this->orderLocator->method('get')->willReturn($this->createMock(OrderInterface::class));
        $this->checkoutSession->expects($this->never())->method('clearStorage');
        $this->checkoutSession->expects($this->never())->method('replaceQuote');
        $this->cartRepository->expects($this->never())->method('save');

        $this->execute();
    }

    public function testAFinishedReturnUsesUpItsToken(): void
    {
        $this->transactionProcessor->method('process')->willReturn($this->payment(Payment::STATE_AUTHORIZED));
        $this->orderLocator->method('get')->willReturn($this->createMock(OrderInterface::class));
        $this->returnToken->expects($this->once())->method('consume');

        $this->execute();
    }

    public function testARestoredCartUsesUpItsToken(): void
    {
        $this->transactionProcessor->method('process')->willReturn($this->payment(Payment::STATE_ABORTED));
        $this->cartRepository->method('get')->willReturn($this->createMock(Quote::class));
        $this->returnToken->expects($this->once())->method('consume');

        $this->execute();
    }

    /**
     * The callback held the lock or Vipps/MobilePay did not answer: nothing was resolved, so the shopper must be
     * able to come back with the same link.
     */
    public function testAnUnresolvedReturnKeepsItsToken(): void
    {
        $this->transactionProcessor->method('process')->willReturn(null);
        $this->returnToken->expects($this->never())->method('consume');

        $this->execute();
    }

    /**
     * Once the browser is back the payment is resolved here, so the cart restore must not run again.
     */
    public function testAlwaysClearsThePendingCartRestore(): void
    {
        $this->transactionProcessor->method('process')->willReturn($this->payment(Payment::STATE_CREATED));
        $this->cartRestoreCookie->expects($this->once())->method('clearPending');

        $this->execute();
    }

    /**
     * The widget's iframe has no session: resolving there would cancel or place in the wrong session, and its
     * cart page would consume the shopper's message. The widget script repeats the request in their tab.
     */
    public function testAnIframeReturnDoesNothing(): void
    {
        $this->fetchDest = 'iframe';
        $this->transactionProcessor->expects($this->never())->method('process');
        $this->cartRepository->expects($this->never())->method('save');
        $this->messageManager->expects($this->never())->method('addWarningMessage');
        $this->cartRestoreCookie->expects($this->never())->method('clearPending');

        $this->execute();

        $this->assertInstanceOf(Raw::class, $this->result);
        $this->assertNull($this->redirectPath);
    }

    private function payment(string $state): Payment
    {
        return new Payment([Payment::STATE => $state]);
    }

    private function execute(): void
    {
        $redirect = $this->createMock(Redirect::class);
        $redirect->method('setPath')->willReturnCallback(function ($path) use ($redirect) {
            $this->redirectPath = $path;
            return $redirect;
        });
        $raw = $this->createMock(Raw::class);
        $raw->method('setContents')->willReturnSelf();
        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->method('create')->willReturnCallback(
            fn ($type) => $type === ResultFactory::TYPE_RAW ? $raw : $redirect
        );

        $vippsQuote = $this->createMock(QuoteInterface::class);
        $vippsQuote->method('getReservedOrderId')->willReturn(self::REFERENCE);
        $vippsQuote->method('getQuoteId')->willReturn(42);
        $vippsQuoteRepository = $this->createMock(QuoteRepositoryInterface::class);
        $vippsQuoteRepository->method('loadByOrderId')->with(self::REFERENCE)->willReturn($vippsQuote);

        $config = $this->createMock(Config::class);
        $config->method('getValue')->willReturnCallback(
            fn ($field) => $field === 'cancellation_cart_persistence' ? $this->cartPersistence : null
        );
        $config->method('getTitle')->willReturn('Vipps');

        $request = $this->createMock(HttpRequest::class);
        $request->method('getRequestString')->willReturn('/vipps/payment/fallback');
        $request->method('getParam')->willReturnCallback(
            fn ($name, $default = null) => $name === 'reference' ? $this->reference : $default
        );
        $request->method('getHeader')->willReturnCallback(
            fn ($name) => $name === 'Sec-Fetch-Dest' ? $this->fetchDest : false
        );

        $this->result = (new Fallback(
            $resultFactory,
            $request,
            $this->checkoutSession,
            $this->transactionProcessor,
            $this->cartRepository,
            $this->messageManager,
            $vippsQuoteRepository,
            $this->createMock(OrderManagementInterface::class),
            $this->orderLocator,
            $this->createMock(Compliance::class),
            $this->createMock(LoggerInterface::class),
            $config,
            new StatusVisitor(),
            $this->authoriseProxy,
            $this->cartRestoreCookie,
            $this->returnToken
        ))->execute();
    }
}
