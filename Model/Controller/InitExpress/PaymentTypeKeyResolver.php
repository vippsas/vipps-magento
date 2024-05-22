<?php

declare(strict_types=1);

namespace Vipps\Payment\Model\Controller\InitExpress;

use Magento\Quote\Api\Data\CartInterface;
use Vipps\Payment\Gateway\Request\Initiate\InitiateBuilderInterface;

class PaymentTypeKeyResolver
{
    public function resolve(CartInterface $quote): string
    {
        return $quote->getIsVirtual()
            ? InitiateBuilderInterface::PAYMENT_TYPE_REGULAR_PAYMENT
            : InitiateBuilderInterface::PAYMENT_TYPE_EXPRESS_CHECKOUT;
    }
}
