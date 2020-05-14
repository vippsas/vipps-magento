<?php
/**
 * Copyright Vipps
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

namespace Vipps\Payment\Model\Quote;

use Vipps\Payment\Gateway\Config\Config;

/**
 * Validate shipping method on allowance for Vipps payment.
 */
class ShippingMethodValidator
{
    /**
     * @var Config
     */
    private $config;

    /**
     * ShippingMethodValidator constructor.
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param string $methodCode
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isValid($methodCode)
    {
        $disabledShippingMethods = array_filter(
            explode(',', $this->config->getValue('disallowed_shipping_methods'))
        );
        return !in_array($methodCode, $disabledShippingMethods);
    }
}
