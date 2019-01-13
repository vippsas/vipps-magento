<?php
/**
 * Copyright 2018 Vipps
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
 *  documentation files (the "Software"), to deal in the Software without restriction, including without limitation
 *  the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software,
 *  and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED
 *  TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL
 *  THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
 *  CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 *  IN THE SOFTWARE.
 *
 */

namespace Vipps\Payment\Api\Monitoring;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartInterface;
use Vipps\Payment\Api\Monitoring\Data\QuoteInterface;
use Vipps\Payment\Model\Monitoring\Quote;
use Vipps\Payment\Model\Monitoring\QuoteManagement;

/**
 * Interface QuoteManagementInterface
 * @api
 */
interface QuoteManagementInterface
{
    /**
     * @param CartInterface $cart
     * @return QuoteInterface
     */
    public function create(CartInterface $cart);

    /**
     * @param CartInterface $cart
     * @return Quote
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function getByQuote(CartInterface $cart);

    /**
     * Loads Vipps monitoring as extension attribute.
     *
     * @param CartInterface $quote
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function loadExtensionAttribute(CartInterface $quote);
}
