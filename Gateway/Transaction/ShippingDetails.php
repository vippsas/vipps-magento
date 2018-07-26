<?php
/**
 * Copyright 2018 Vipps
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
namespace Vipps\Payment\Gateway\Transaction;

use Magento\Framework\DataObject;

/**
 * Class ShippingDetails
 * @package Vipps\Payment\Gateway\Transaction
 */
class ShippingDetails extends DataObject
{
    /**
     * @var string
     */
    const NORWEGIAN_COUNTRY_ID = 'NO';

    /**
     * @var string
     */
    const SHIPPING_COST = 'shippingCost';

    /**
     * @var string
     */
    const SHIPPING_METHOD = 'shippingMethod';

    /**
     * @var string
     */
    const SHIPPING_METHOD_ID = 'shippingMethodId';

    /**
     * @var string
     */
    const CITY = 'city';

    /**
     * @var string
     */
    const COUNTRY = 'country';

    /**
     * @var string
     */
    const STREET = 'street';

    /**
     * @var string
     */
    const ADDRESS_LINE_1 = 'addressLine1';

    /**
     * @var string
     */
    const ADDRESS_LINE_2 = 'addressLine2';

    /**
     * @var string
     */
    const POST_CODE = 'postCode';

    /**
     * @var string
     */
    const ZIP_CODE = 'zipCode';

    /**
     * @var string
     */
    const ADDRESS = 'address';

    /**
     * @return string
     */
    public function getShippingCost()
    {
        return $this->getData(self::SHIPPING_COST);
    }

    /**
     * @return string
     */
    public function getShippingMethod()
    {
        return $this->getData(self::SHIPPING_METHOD);
    }

    /**
     * @return string
     */
    public function getShippingMethodId()
    {
        return $this->getData(self::SHIPPING_METHOD_ID);
    }

    /**
     * @return string
     */
    public function getCity()
    {
        return $this->getData(self::ADDRESS)[self::CITY];
    }

    /**
     * @return string
     */
    public function getCountry()
    {
        return $this->getData(self::ADDRESS)[self::COUNTRY];
    }

    /**
     * @return string
     */
    public function getPostcode()
    {
        //Added condition to support zipCode in special cases
        return $this->_data[self::ADDRESS][self::POST_CODE] ?? $this->_data[self::ADDRESS][self::ZIP_CODE];
    }

    /**
     * @return string
     */
    public function getStreet()
    {
        return $this->getAddressLine1() . PHP_EOL . $this->getAddressLine2();
    }

    /**
     * @return string
     */
    public function getAddressLine1()
    {
        return $this->getData(self::ADDRESS)[self::ADDRESS_LINE_1];
    }

    /**
     * @return string
     */
    public function getAddressLine2()
    {
        return $this->getData(self::ADDRESS)[self::ADDRESS_LINE_2] ?? '';
    }
}
