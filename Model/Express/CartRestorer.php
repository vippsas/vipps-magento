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

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Vipps\Payment\GatewayEpayment\Config\Config;
use Vipps\Payment\Model\QuoteRepository;

/**
 * Restores a shopper's cart after an abandoned Vipps/MobilePay Express payment.
 *
 * Shared by both restore entry points: the server-side cart-page observer and the
 * AJAX RestoreCart controller (which covers the bfcache back-button case).
 *
 * The shopper's browser coming back is not proof that the payment is over. On mobile it is
 * approved in the Vipps/MobilePay app, so the store can be shown again while the payment is
 * still live or already authorised. This class therefore only hands the cart back and never
 * touches the monitoring quote: FetchOrderFromVipps already reads the real payment state and
 * places, cancels or expires it from there. Writing the payment off here instead cost shoppers
 * their orders while the amount stayed reserved at Vipps.
 */
class CartRestorer
{
    public function __construct(
        private readonly QuoteRepository $vippsQuoteRepository,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly Session $checkoutSession,
        private readonly ManagerInterface $messageManager,
        private readonly Config $config
    ) {
    }

    /**
     * Reactivate the cart quote so the shopper can carry on shopping.
     *
     * The reserved order id is cleared so a further checkout gets a fresh reference. The
     * monitoring quote keeps its own copy, and TransactionProcessor::placeOrder() puts it back
     * on the cart if the original payment is completed after all.
     *
     * @param int $quoteId
     * @return bool True if the cart was restored, false if there was nothing to restore
     *              (e.g. the payment had already been processed).
     */
    public function restore(int $quoteId): bool
    {
        if (!$quoteId) {
            return false;
        }

        try {
            // Confirms a payment is still outstanding for this cart. Its status is left alone.
            $this->vippsQuoteRepository->loadNewByQuote($quoteId);

            /** @var Quote $quote */
            $quote = $this->cartRepository->get($quoteId);
            $quote->setIsActive(true);
            $quote->setReservedOrderId(null);
            $this->cartRepository->save($quote);
            $this->checkoutSession->replaceQuote($quote);

            // Nothing has been read from Vipps/MobilePay at this point, so the payment may be
            // unapproved, already approved, aborted or expired. Say only what is true of all four:
            // it is still ours to resolve. Claiming it failed invites a second payment, and
            // telling the shopper to go and complete it may be asking for the impossible.
            $this->messageManager->addNoticeMessage(
                __(
                    'Your cart has been restored. Any payment you have already started in %1 is'
                    . ' still being processed, so please wait a few minutes before paying again.',
                    $this->config->getTitle()
                )
            );

            return true;
        } catch (NoSuchEntityException $e) {
            // Payment was already processed, nothing to restore.
            return false;
        }
    }
}
