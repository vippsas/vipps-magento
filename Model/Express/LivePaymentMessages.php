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

use Magento\Framework\Phrase;
use Vipps\Payment\GatewayEpayment\Config\Config;

/**
 * Shopper messages for an already approved payment on their cart.
 */
class LivePaymentMessages
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function orderPlaced(): Phrase
    {
        return __(
            'Your payment in %1 was approved, and your order has been placed.',
            $this->config->getTitle()
        );
    }

    public function authorisedPending(): Phrase
    {
        return __(
            'You have already approved a payment for this cart in %1, and your order is being completed.'
            . ' Please wait a few minutes rather than paying again.',
            $this->config->getTitle()
        );
    }
}
