<?php

declare(strict_types=1);

namespace Vipps\Payment\Model\Fallback\Authorise;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Api\Fallback\AuthoriseInterface;

class Epayment implements AuthoriseInterface
{
    public function do(RequestInterface $request, QuoteInterface $vippsQuote): void
    {
        if (!$request->getParam('reference')) {
            throw new LocalizedException(__('Invalid request parameters'));
        }

        if ($vippsQuote->getReservedOrderId() !== $request->getParam('reference', '')) {
            throw new LocalizedException(__('Invalid request'));
        }
    }

    public function getOrderId(RequestInterface $request): string
    {
        return $request->getParam('reference');
    }
}
