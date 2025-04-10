<?php

declare(strict_types=1);

namespace Vipps\Payment\Model\Fallback\Authorise;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Api\Fallback\AuthoriseInterface;

class Commerce implements AuthoriseInterface
{
    /**
     * @inheritDoc
     */
    public function do(RequestInterface $request, QuoteInterface $vippsQuote): void
    {
        $orderId = $this->getOrderId($request);
        if (!$orderId || !$request->getParam('auth_token')) {
            throw new LocalizedException(__('Invalid request parameters'));
        }

        if ($vippsQuote->getAuthToken() !== $request->getParam('auth_token', '')) {
            throw new LocalizedException(__('Invalid request'));
        }
    }

    /**
     * @inheritDoc
     */
    public function getOrderId(RequestInterface $request): string
    {
        $orderId = (string) $request->getParam('order_id');
        if (!$orderId) {
            $orderId = (string) $request->getParam('reference');
        }

        return $orderId;
    }
}
