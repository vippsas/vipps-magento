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
namespace Vipps\Payment\Model\Profiling;

/**
 * Interface TypeInterface
 * @package Vipps\Payment\Model\Profiling
 */
interface TypeInterface
{
    /**
     * Values/labels for all request types we are sending to Vipps
     *
     * @var string
     */
    const INITIATE_PAYMENT = 'initiate';
    const INITIATE_PAYMENT_LABEL = 'Initiate Payment';

    const GET_PAYMENT_DETAILS = 'details';
    const GET_PAYMENT_DETAILS_LABEL = 'Get Payment Details';

    const GET_ORDER_STATUS = 'status';
    const GET_ORDER_STATUS_LABEL = 'Get Order Status';

    const CAPTURE = 'capture';
    const CAPTURE_LABEL = 'Capture';

    const REFUND = 'refund';
    const REFUND_LABEL = 'Refund';

    const CANCEL = 'cancel';
    const CANCEL_LABEL = 'Cancel';

    const VOID = 'void';
    const VOID_LABEL = 'Void';
}
