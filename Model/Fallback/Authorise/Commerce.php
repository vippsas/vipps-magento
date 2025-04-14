<?php

declare(strict_types=1);

namespace Vipps\Payment\Model\Fallback\Authorise;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Api\Fallback\AuthoriseInterface;

class Commerce implements AuthoriseInterface
{
    public function do(RequestInterface $request, QuoteInterface $vippsQuote): void
    {
        if (!$request->getParam('order_id') ||
            !$request->getParam('auth_token')
        ) {
            throw new LocalizedException(__('Invalid request parameters'));
        }

        if ($vippsQuote->getAuthToken() !== $request->getParam('auth_token', '')) {
            throw new LocalizedException(__('Invalid request'));
        }
    }

    public function getOrderId(RequestInterface $request): string
    {
        return $request->getParam('order_id');
    }
}
