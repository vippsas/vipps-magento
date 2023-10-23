<?php
/**
 * Copyright 2022 Vipps
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
namespace Vipps\Payment\GatewayEcomm\Data;

use Magento\Framework\DataObject;

/**
 * Class Amount
 * @package Vipps\Payment\GatewayEcomm\Data
 * @SuppressWarnings(PHPMD.BooleanGetMethodName)
 */
class Amount extends DataObject
{
    /**
     * @var string
     */
    const CURRENCY = 'currency';

    /**
     * @var string
     */
    const VALUE = 'value';

    /**
     * @return string|null
     */
    public function getCurrency()
    {
        return $this->getData(self::CURRENCY);
    }

    /**
     * @return int|null
     */
    public function getValue()
    {
        return $this->getData(self::VALUE);
    }
}
