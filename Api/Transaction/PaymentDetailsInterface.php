<?php

declare(strict_types=1);

namespace Vipps\Payment\Api\Transaction;

interface PaymentDetailsInterface
{
    public function get(string $orderId);
}
