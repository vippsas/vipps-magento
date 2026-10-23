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

namespace Vipps\Payment\Model;

use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Model\ResourceModel\Quote as QuoteResource;

/**
 * Issues and checks the token that authorises a shopper's return from Vipps/MobilePay.
 *
 * The payment reference is the order increment ID, which is sequential, so it cannot authorise anything on its
 * own. The token is generated when the payment is initiated, carried in the return URL, and stored on the
 * monitoring quote. It is single-use and expires, so one leaked from a log or browser history is of little use.
 */
class ReturnToken
{
    /**
     * Long enough for a shopper who returns after the payment itself has expired to still get their cart back.
     */
    public const LIFETIME = 86400;

    /**
     * Payment additional information key that carries the token from the request builder to the response handler.
     */
    public const PAYMENT_KEY = 'vipps_return_token';

    public function __construct(
        private readonly QuoteResource $quoteResource
    ) {
    }

    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function isValid(QuoteInterface $vippsQuote, ?string $token): bool
    {
        $expected = (string)$vippsQuote->getReturnToken();
        if ($expected === '' || !$token || !hash_equals($expected, $token)) {
            return false;
        }

        $createdAt = (string)$vippsQuote->getCreatedAt();
        if ($createdAt === '') {
            return false;
        }

        $issuedAt = (new \DateTimeImmutable($createdAt, new \DateTimeZone('UTC')))->getTimestamp();

        return time() - $issuedAt <= self::LIFETIME;
    }

    /**
     * Clears the token, but only if it is still the one this request validated.
     *
     * A targeted update rather than a model save: the model was loaded before the payment was read from
     * Vipps/MobilePay, and saving it whole could put back a status or order the callback has since written.
     * It also means two simultaneous returns cannot both use the same token.
     *
     * @return bool false when another request used the token first
     */
    public function consume(QuoteInterface $vippsQuote): bool
    {
        $token = (string)$vippsQuote->getReturnToken();
        if ($token === '') {
            return false;
        }

        $updated = $this->quoteResource->getConnection()->update(
            $this->quoteResource->getMainTable(),
            [QuoteInterface::RETURN_TOKEN => null],
            [
                QuoteInterface::ENTITY_ID . ' = ?' => (int)$vippsQuote->getEntityId(),
                QuoteInterface::RETURN_TOKEN . ' = ?' => $token,
            ]
        );
        $vippsQuote->setReturnToken(null);

        return $updated > 0;
    }
}
