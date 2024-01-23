<?php

declare(strict_types=1);

namespace Vipps\Payment\Model\Fallback;

use Magento\Framework\App\RequestInterface;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Api\Fallback\AuthoriseInterface;
use Vipps\Payment\Model\Config\ConfigVersionPool;

class AuthoriseProxy implements AuthoriseInterface
{
    private ConfigVersionPool $configVersionPool;

    public function __construct(ConfigVersionPool $configVersionPool)
    {
        $this->configVersionPool = $configVersionPool;
    }

    private function get(): AuthoriseInterface
    {
        return $this->configVersionPool->get();
    }

    public function do(RequestInterface $request, QuoteInterface $vippsQuote): void
    {
        $this->get()->do($request, $vippsQuote);
    }

    public function getOrderId(RequestInterface $request): string
    {
        return $this->get()->getOrderId($request);
    }
}
