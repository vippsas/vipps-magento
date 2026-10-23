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

namespace Vipps\Payment\GraphQl;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Api\QuoteRepositoryInterface;
use Vipps\Payment\Model\ReturnToken;

/**
 * Authorises the GraphQL calls a headless storefront makes when the shopper returns from Vipps/MobilePay.
 *
 * The order number alone is sequential and public, so the caller must also present the return token from the
 * return URL, and a customer's cart is only available to that customer.
 */
class ReturnAuthorizer
{
    public function __construct(
        private readonly QuoteRepositoryInterface $vippsQuoteRepository,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ReturnToken $returnToken
    ) {
    }

    /**
     * @return array{0: QuoteInterface, 1: CartInterface}
     * @throws GraphQlAuthorizationException
     */
    public function authorize(string $orderNumber, string $token, ContextInterface $context): array
    {
        try {
            $vippsQuote = $this->vippsQuoteRepository->loadByOrderId($orderNumber);
            $quote = $this->cartRepository->get((int)$vippsQuote->getQuoteId());
        } catch (NoSuchEntityException $e) {
            throw $this->denied();
        }

        // An unknown order number and a wrong token fail alike, so the response says nothing about which
        // order numbers exist.
        if (!$this->returnToken->isValid($vippsQuote, $token)) {
            throw $this->denied();
        }

        $customerId = (int)$quote->getCustomerId();
        if ($customerId && $customerId !== (int)$context->getUserId()) {
            throw $this->denied();
        }

        return [$vippsQuote, $quote];
    }

    private function denied(): GraphQlAuthorizationException
    {
        return new GraphQlAuthorizationException(__('The current user cannot access this payment.'));
    }
}
