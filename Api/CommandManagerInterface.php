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
namespace Vipps\Payment\Api;

use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Model\InfoInterface;
use Vipps\Payment\Gateway\Exception\VippsException;

/**
 * Interface CommandManagerInterface
 * @package Vipps\Payment\Api
 * @api
 */
interface CommandManagerInterface
{
    /**
     * Initiate payment action.
     *
     * @param InfoInterface $payment
     * @param array $arguments
     *
     * @return ResultInterface|null
     */
    public function initiatePayment(InfoInterface $payment, $arguments);

    /**
     * Get Payment details command.
     *
     * @param array $arguments
     *
     * @return mixed
     * @throws VippsException
     */
    public function getPaymentDetails($arguments = []);

    /**
     * Method to execute cancel Command.
     *
     * @param InfoInterface $payment
     * @param array $arguments
     *
     * @return mixed
     */
    public function cancel(InfoInterface $payment, $arguments = []);
}
