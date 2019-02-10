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
namespace Vipps\Payment\Gateway\Exception;

use Magento\Framework\Exception\LocalizedExceptionFactory;

/**
 * Class ExceptionFactory
 * @package Vipps\Payment\Gateway\Exception
 */
class ExceptionFactory
{
    /**
     * @var LocalizedExceptionFactory
     */
    private $localizedExceptionFactory;

    /**
     * @var array
     */
    private static $visibleErrors = [41, 42, 43, 44, 45, 81, 82];

    /**
     * ExceptionFactory constructor.
     *
     * @param LocalizedExceptionFactory $localizedExceptionFactory
     */
    public function __construct(
        LocalizedExceptionFactory $localizedExceptionFactory
    ) {
        $this->localizedExceptionFactory = $localizedExceptionFactory;
    }

    /**
     * @var array
     */
    private static $errorCodesByGroups = [
        InvalidRequestException::class => [],
        PaymentException::class => [41, 42, 43, 44, 45, 51, 52, 53, 61, 62, 63, 71, 72, 73, 74],
        VippsErrorException::class => [91, 92, 98, 99],
        CustomerException::class => [81, 82],
        MerchantException::class => [21, 22, 31, 32, 33, 34, 35, 36, 37],
    ];

    /**
     * Method to get Exception by code with provided message.
     *
     * @param $errorCode
     * @param $errorMessage
     *
     * @return mixed
     */
    public function create($errorCode, $errorMessage)
    {
        $groupName = $this->findErrorGroupByCode($errorCode);
        if (!$groupName) {
            return $this->localizedExceptionFactory->create([
                'phrase' => __($errorMessage),
                'cause' => null,
                'code' => $errorCode
            ]);
        }

        $exceptionObject = new $groupName(__($errorMessage), null, (int)$errorCode); //@codingStandardsIgnoreLine
        return $exceptionObject;
    }

    /**
     * Method to find vipps error group by code.
     *
     * @param $errorCode
     *
     * @return bool|int|string
     */
    private function findErrorGroupByCode($errorCode)
    {
        $errorCodeInt = (int) $errorCode;
        if (!$errorCodeInt) {
            return key(self::$errorCodesByGroups);
        }
        foreach (self::$errorCodesByGroups as $groupName => $errorsList) {
            if (in_array($errorCodeInt, $errorsList)) {
                return $groupName;
            }
        }
        return VippsException::class;
    }

    /**
     * Getting error Message by code if this message is in the visible list.
     *
     * @param $errorCode
     * @param $errorMessage
     *
     * @return \Magento\Framework\Phrase|null
     */
    public function getMessageByErrorCode($errorCode, $errorMessage)
    {
        $message = __('Couldn\'t process this request. Please try again later or contact a store administrator.');
        return in_array($errorCode, self::$visibleErrors) ? $errorMessage : $message;
    }
}
