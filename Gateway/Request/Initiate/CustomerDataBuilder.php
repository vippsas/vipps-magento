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
namespace Vipps\Payment\Gateway\Request\Initiate;

use Vipps\Payment\Gateway\Request\SubjectReader;

/**
 * Class CustomerInfo
 * @package Vipps\Payment\Gateway\Request\InitiateData
 */
class CustomerDataBuilder implements InitiateBuilderInterface
{
    /**
     * Customer info block name
     *
     * @var string
     */
    private static $customerInfo = 'customerInfo';

    /**
     * Mobile number of the user who has to pay for the transaction from Vipps. Allowed format: xxxxxxxx. OPTIONAL.
     *
     * @var string
     */
    private static $mobileNumber = 'mobileNumber';

    /**
     * @var SubjectReader
     */
    private $subjectReader;

    public function __construct(
        SubjectReader $subjectReader
    ) {
        $this->subjectReader = $subjectReader;
    }

    /**
     * Get customer related data for Initiate payment request.
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        return [
            self::$customerInfo => [
                self::$mobileNumber => $this->subjectReader->readTelephone($buildSubject)
            ]
        ];
    }
}
