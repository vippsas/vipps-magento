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
 * Class UserDetails
 * @package Vipps\Payment\Gateway\Transaction
 */
class UserDetails extends DataObject
{
    /**
     * @var string
     */
    const EMAIL = 'email';

    /**
     * @var string
     */
    const FIRST_NAME = 'firstName';

    /**
     * @var string
     */
    const LAST_NAME = 'lastName';

    /**
     * @var string
     */
    const MOBILE_NUMBER = 'mobileNumber';

    /**
     * @var string
     */
    const SSN = 'ssn';

    /**
     * @var string
     */
    const BANK_ID_VERIFIED = 'bankIdVerified';

    /**
     * @var string
     */
    const DATE_OF_BIRTH = 'dateOfBirth';

    /**
     * @var string
     */
    const USER_ID = 'userId';

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->getData(self::EMAIL);
    }

    /**
     * @return string
     */
    public function getFirstName()
    {
        return $this->getData(self::FIRST_NAME);
    }

    /**
     * @return string
     */
    public function getLastName()
    {
        return $this->getData(self::LAST_NAME);
    }

    /**
     * @return string
     */
    public function getMobileNumber()
    {
        return $this->getData(self::MOBILE_NUMBER);
    }

    /**
     * @return string
     */
    public function getSsn()
    {
        return $this->hasData(self::SSN);
    }

    /**
     * @return string
     */
    public function getBankIdVerified()
    {
        return $this->hasData(self::BANK_ID_VERIFIED);
    }

    /**
     * @return string
     */
    public function getDateOfBirth()
    {
        return $this->hasData(self::DATE_OF_BIRTH);
    }

    /**
     * @return string
     */
    public function getUserId()
    {
        return $this->hasData(self::USER_ID);
    }
}
