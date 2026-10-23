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

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;

/**
 * Reads and writes the cookies that drive Express cart restoration.
 *
 * The pending-quote cookie carries the cart's quote id from InitExpress to the restore entry points
 * (the cart-page observer and the AJAX RestoreCart controller). The restored flag tells the storefront
 * JS that a server-side restore already happened so it can refresh its cart state without restoring
 * again. Cookie names are kept in sync with view/frontend/web/js/vipps-cart-restore.js.
 *
 * The quote id is sequential and the cookie is set by the browser, so the pending value is signed. Without
 * that, anyone could name another shopper's quote and have it restored into their own session.
 */
class CartRestoreCookie
{
    public const PENDING_COOKIE = 'vipps_pending_quote_id';
    public const RESTORED_COOKIE = 'vipps_cart_restored';

    private const PENDING_LIFETIME = 7200;
    private const RESTORED_LIFETIME = 60;

    public function __construct(
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function setPending(int $quoteId): void
    {
        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
            ->setDuration(self::PENDING_LIFETIME)
            ->setPath('/');
        $value = $quoteId . ':' . $this->sign((string)$quoteId);
        $this->cookieManager->setPublicCookie(self::PENDING_COOKIE, $value, $metadata);
    }

    public function getPending(): int
    {
        $parts = explode(':', (string)$this->cookieManager->getCookie(self::PENDING_COOKIE), 2);
        if (count($parts) !== 2 || !ctype_digit($parts[0]) || !hash_equals($this->sign($parts[0]), $parts[1])) {
            return 0;
        }

        return (int)$parts[0];
    }

    public function clearPending(): void
    {
        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()->setPath('/');
        $this->cookieManager->deleteCookie(self::PENDING_COOKIE, $metadata);
    }

    private function sign(string $quoteId): string
    {
        return $this->encryptor->hash(self::PENDING_COOKIE . ':' . $quoteId);
    }

    public function markRestored(): void
    {
        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
            ->setDuration(self::RESTORED_LIFETIME)
            ->setPath('/');
        $this->cookieManager->setPublicCookie(self::RESTORED_COOKIE, '1', $metadata);
    }
}
