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
namespace Vipps\Payment\Gateway\Request;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Class MerchantDataBuilder
 * @package Vipps\Payment\Gateway\Request
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class MerchantDataBuilder implements BuilderInterface
{
    /**
     * Merchant info block name
     *
     * @var string
     */
    private static $merchantInfo = 'merchantInfo';

    /**
     * Identifies a merchant sales channel i.e. website, mobile app etc. Value must be less than or equal to
     * 6 characters.
     *
     * @var string
     */
    private static $merchantSerialNumber = 'merchantSerialNumber';

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * MerchantDataBuilder constructor.
     *
     * @param ConfigInterface $config
     */
    public function __construct(
        ConfigInterface $config
    ) {
        $this->config = $config;
    }

    /**
     * Get merchant related data for request.
     *
     * @param array $buildSubject
     *
     * @return array
     * @throws \Exception
     */
    public function build(array $buildSubject) //@codingStandardsIgnoreLine
    {
        return [
            self::$merchantInfo => [
                self::$merchantSerialNumber => $this->config->getValue('merchant_serial_number'),
            ]
        ];
    }
}
