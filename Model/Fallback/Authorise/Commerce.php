<?php

declare(strict_types=1);

namespace Vipps\Payment\Model\Fallback\Authorise;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Api\Fallback\AuthoriseInterface;
use Vipps\Payment\Model\ReturnToken;

class Commerce implements AuthoriseInterface
{
    public function __construct(
        private readonly ReturnToken $returnToken
    ) {
    }

    public function do(RequestInterface $request, QuoteInterface $vippsQuote): void
    {
        if (!$request->getParam('reference')) {
            throw new LocalizedException(__('Invalid request parameters'));
        }

        if ($vippsQuote->getReservedOrderId() !== $request->getParam('reference', '')) {
            throw new LocalizedException(__('Invalid request'));
        }

        // The quote was looked up by the reference, so the check above cannot fail. The token is what proves
        // this is the shopper's own return rather than someone walking the order number sequence.
        if (!$this->returnToken->isValid($vippsQuote, (string)$request->getParam('token', ''))) {
            throw new LocalizedException(__('Invalid request'));
        }
    }

    public function getOrderId(RequestInterface $request): string
    {
        return $request->getParam('reference');
    }
}
