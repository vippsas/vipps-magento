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

use Magento\Sales\Api\Data\OrderInterface;

/**
 * Outcome of LivePaymentGuard for an Express request.
 */
class LivePaymentDecision
{
    public const PROCEED = 'proceed';
    public const ORDER_PLACED = 'order_placed';
    public const AUTHORISED_PENDING = 'authorised_pending';

    /**
     * @param string $outcome one of the constants above
     * @param mixed $paymentResponse the new payment's initiation response, when one was created
     * @param OrderInterface|null $order the order an approved payment produced
     */
    private function __construct(
        private readonly string $outcome,
        private readonly mixed $paymentResponse = null,
        private readonly ?OrderInterface $order = null
    ) {
    }

    /**
     * A new payment was created.
     */
    public static function proceed(mixed $paymentResponse): self
    {
        return new self(self::PROCEED, $paymentResponse);
    }

    /**
     * An earlier payment for the cart was approved and is now an order.
     */
    public static function orderPlaced(OrderInterface $order): self
    {
        return new self(self::ORDER_PLACED, null, $order);
    }

    /**
     * An earlier payment for the cart was approved but is not an order yet.
     */
    public static function authorisedPending(): self
    {
        return new self(self::AUTHORISED_PENDING);
    }

    public function getOutcome(): string
    {
        return $this->outcome;
    }

    public function isProceed(): bool
    {
        return $this->outcome === self::PROCEED;
    }

    public function isOrderPlaced(): bool
    {
        return $this->outcome === self::ORDER_PLACED;
    }

    public function isAuthorisedPending(): bool
    {
        return $this->outcome === self::AUTHORISED_PENDING;
    }

    public function getPaymentResponse(): mixed
    {
        return $this->paymentResponse;
    }

    public function getOrder(): ?OrderInterface
    {
        return $this->order;
    }
}
