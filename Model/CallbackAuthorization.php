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

/**
 * Checks the token Vipps/MobilePay presents on a server-to-server callback against the one stored at initiation.
 *
 * Fails closed: a missing stored token or a missing presented token is never a match, so a payment that was
 * initiated without a token (or before tokens were persisted) cannot be called back at all.
 */
class CallbackAuthorization
{
    /**
     * @param string|null $storedToken
     * @param string|null $presentedToken
     *
     * @return bool
     */
    public function isAuthorized(?string $storedToken, ?string $presentedToken): bool
    {
        $storedToken = (string)$storedToken;
        $presentedToken = (string)$presentedToken;

        if ($storedToken === '' || $presentedToken === '') {
            return false;
        }

        return hash_equals($storedToken, $presentedToken);
    }
}
