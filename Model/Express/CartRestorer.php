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
use Vipps\Payment\Api\Data\QuoteStatusInterface;
use Vipps\Payment\GatewayEpayment\Config\Config;
use Vipps\Payment\Model\QuoteRepository;

/**
 * Restores a shopper's cart after an abandoned Vipps/MobilePay Express payment.
 *
 * Shared by both restore entry points: the server-side cart-page observer and the
 * AJAX RestoreCart controller (which covers the bfcache back-button case).
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
     * Cancel the pending Vipps monitoring quote and reactivate the cart quote.
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
            $vippsQuote = $this->vippsQuoteRepository->loadNewByQuote($quoteId);
            $vippsQuote->setStatus(QuoteStatusInterface::STATUS_CANCELED);
            $this->vippsQuoteRepository->save($vippsQuote);

            /** @var Quote $quote */
            $quote = $this->cartRepository->get($quoteId);
            $quote->setIsActive(true);
            $quote->setReservedOrderId(null);
            $this->cartRepository->save($quote);
            $this->checkoutSession->replaceQuote($quote);

            $this->messageManager->addWarningMessage(
                __('Your order was cancelled in %1.', $this->config->getTitle())
            );

            return true;
        } catch (NoSuchEntityException $e) {
            // Payment was already processed — nothing to restore.
            return false;
        }
    }
}
