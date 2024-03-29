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
namespace Vipps\Payment\Model;

use Vipps\Payment\Gateway\Exception\AuthenticationException;
use Magento\Framework\Exception\CouldNotSaveException;

/**
 * Interface TokenProviderInterface
 * @package Vipps\Payment\Model
 * @api
 */
interface TokenProviderInterface
{
    /**
     * Method to get valid token string.
     *
     * @param null $scopeId
     *
     * @return string
     * @throws AuthenticationException
     */
    public function get($scopeId = null);

    /**
     * Method to regenerate access token from Vipps and save it to storage.
     *
     * @param null $scopeId
     *
     * @throws CouldNotSaveException
     * @throws AuthenticationException
     */
    public function regenerate($scopeId = null);
}
