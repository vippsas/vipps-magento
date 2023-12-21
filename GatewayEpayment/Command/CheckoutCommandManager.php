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
namespace Vipps\Payment\GatewayEpayment\Command;

use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Framework\Exception\NotFoundException;
use Vipps\Payment\Api\CheckoutCommandManagerInterface;

/**
 * Class CommandManager
 * @package Vipps\Payment\Model
 */
class CheckoutCommandManager extends CommandManager implements CheckoutCommandManagerInterface
{
    /**
     * {@inheritdoc}
     *
     * @param InfoInterface $payment
     * @param array $arguments
     *
     * @return ResultInterface|mixed|null
     * @throws CommandException
     * @throws NotFoundException
     */
    public function initSession(InfoInterface $payment, $arguments = [])
    {
        return $this->executeByCode('init-session', $payment, $arguments);
    }

    public function getSession($reference, $arguments = [])
    {
        $arguments['reference'] = $reference;

        return $this->executeByCode('get-session', null, $arguments);
    }

    public function adjustAuthorization(InfoInterface $payment, $arguments = [])
    {
        return $this->executeByCode('adjust-authorization', $payment, $arguments);
    }
}
