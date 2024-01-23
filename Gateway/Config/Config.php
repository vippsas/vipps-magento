<?php
/**
 * Copyright 2020 Vipps
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

namespace Vipps\Payment\Gateway\Config;

use Magento\Payment\Gateway\Config\Config as OriginConfig;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Vipps\Payment\Model\Method\Vipps;

class Config extends OriginConfig
{
    private StoreManagerInterface $storeManager;

    public function __construct(
        ScopeConfigInterface  $scopeConfig,
        StoreManagerInterface $storeManager,
    ) {
        parent::__construct($scopeConfig, Vipps::METHOD_CODE);

        $this->storeManager = $storeManager;
    }

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getValue($field, $storeId = null)
    {
        if (null === $storeId) {
            $storeId = $this->storeManager->getStore()->getId();
        }

        return OriginConfig::getValue($field, $storeId);
    }
}
