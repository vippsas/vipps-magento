<?php

declare(strict_types=1);

namespace Vipps\Payment\Api\Fallback;

use Magento\Framework\App\RequestInterface;
use Vipps\Payment\Api\Data\QuoteInterface;

interface AuthoriseInterface
{
    public function do(RequestInterface $request, QuoteInterface $vippsQuote): void;

    public function getOrderId(RequestInterface $request): string;
}
