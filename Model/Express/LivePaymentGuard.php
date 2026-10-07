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

namespace Vipps\Payment\Model\Express;

use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Model\LockManager;
use Vipps\Payment\Model\OrderLocator;
use Vipps\Payment\Model\PaymentProvider;
use Vipps\Payment\Model\Quote\CancelFacade;
use Vipps\Payment\Model\QuoteRepository;
use Vipps\Payment\Model\TransactionProcessor;

/**
 * Keeps a cart to one live Express payment: terminates an unapproved one, places the order for an approved one.
 * Failures never block checkout; TransactionProcessor::assertCartHasNoOrder() remains the backstop.
 */
class LivePaymentGuard
{
    private const LOCK_TIMEOUT = 10;

    public function __construct(
        private readonly QuoteRepository $vippsQuoteRepository,
        private readonly PaymentProvider $paymentProvider,
        private readonly CancelFacade $cancelFacade,
        private readonly TransactionProcessor $transactionProcessor,
        private readonly OrderLocator $orderLocator,
        private readonly LockManager $lockManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Settle the cart's live payments, then create the new one unless one is already approved.
     * Locked per cart against double clicks; unlocked before placing, as LockManager holds one lock at a time.
     *
     * @param int|null $quoteId the shopper's cart
     * @param callable $createPayment creates the new payment and returns its initiation response
     *
     * @return LivePaymentDecision
     */
    public function initiate(?int $quoteId, callable $createPayment): LivePaymentDecision
    {
        if (!$quoteId) {
            return LivePaymentDecision::proceed($createPayment());
        }

        $lockName = 'vipps_express_init_' . $quoteId;
        $locked = $this->acquireLock($lockName);

        try {
            $authorised = $this->settleLivePayments($quoteId);
            if ($authorised === null) {
                return LivePaymentDecision::proceed($createPayment());
            }
        } finally {
            if ($locked) {
                $this->lockManager->unlock($lockName);
            }
        }

        return $this->completeAuthorised($authorised);
    }

    /**
     * Place the order for an approved payment on the cart, if any. Never cancels: used on return to the store.
     *
     * @param int $quoteId
     *
     * @return LivePaymentDecision|null null when no live payment for the cart is approved
     */
    public function placeAuthorised(int $quoteId): ?LivePaymentDecision
    {
        foreach ($this->vippsQuoteRepository->getLiveByQuote($quoteId) as $vippsQuote) {
            if ($this->isAuthorised($vippsQuote)) {
                return $this->completeAuthorised($vippsQuote);
            }
        }

        return null;
    }

    /**
     * Terminate the cart's unapproved payments and return the newest approved one, if any.
     */
    private function settleLivePayments(int $quoteId): ?QuoteInterface
    {
        $authorised = null;

        foreach ($this->vippsQuoteRepository->getLiveByQuote($quoteId) as $vippsQuote) {
            $reference = $vippsQuote->getReservedOrderId();

            try {
                $payment = $this->paymentProvider->get($reference);
            } catch (\Throwable $t) {
                $this->logger->error(sprintf(
                    'Could not read payment %s before starting a new one for cart %s: %s',
                    $reference,
                    $quoteId,
                    $t->getMessage()
                ));
                continue;
            }

            if ($payment->isAuthorised()) {
                $authorised ??= $vippsQuote;
                continue;
            }

            if (!$payment->isCreated()) {
                continue;
            }

            if ($this->cancelFacade->terminateIfCreated($vippsQuote)) {
                continue;
            }

            // Approved while being terminated? terminateIfCreated() just re-read the state, so this is fresh.
            if ($authorised === null && $this->isAuthorised($vippsQuote)) {
                $authorised = $vippsQuote;
            }
        }

        return $authorised;
    }

    /**
     * Place an approved payment's order now rather than at the next order fetch.
     */
    private function completeAuthorised(QuoteInterface $vippsQuote): LivePaymentDecision
    {
        // Swallows its own failures, e.g. an amount that no longer matches the cart.
        $this->transactionProcessor->process($vippsQuote);

        try {
            $order = $this->orderLocator->get($vippsQuote->getReservedOrderId())
                ?: $this->orderLocator->getByQuoteId($vippsQuote->getQuoteId());
        } catch (\Throwable $t) {
            $this->logger->error($t->getMessage());
            $order = null;
        }

        return $order
            ? LivePaymentDecision::orderPlaced($order)
            : LivePaymentDecision::authorisedPending();
    }

    private function isAuthorised(QuoteInterface $vippsQuote): bool
    {
        try {
            return $this->paymentProvider->get($vippsQuote->getReservedOrderId())->isAuthorised();
        } catch (\Throwable $t) {
            $this->logger->error($t->getMessage());
            return false;
        }
    }

    /**
     * Proceeds without the lock rather than block the shopper.
     */
    private function acquireLock(string $lockName): bool
    {
        try {
            if ($this->lockManager->lock($lockName, self::LOCK_TIMEOUT)) {
                return true;
            }
        } catch (\Throwable $t) {
            $this->logger->error($t->getMessage());
        }

        $this->logger->warning(sprintf('Starting an Express payment without lock %s.', $lockName));

        return false;
    }
}
