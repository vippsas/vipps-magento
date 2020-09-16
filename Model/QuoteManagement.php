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

namespace Vipps\Payment\Model;

use Magento\Quote\Api\Data\CartInterface;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Api\QuoteManagementInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Class QuoteRepository
 */
class QuoteManagement implements QuoteManagementInterface
{
    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var \Vipps\Payment\Model\QuoteRepository
     */
    private $quoteRepository;

    /**
     * QuoteManagement constructor.
     * @param QuoteFactory $quoteFactory
     * @param QuoteRepository $quoteRepository
     */
    public function __construct(
        QuoteFactory $quoteFactory,
        QuoteRepository $quoteRepository
    ) {
        $this->quoteFactory = $quoteFactory;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @param CartInterface $cart
     * @return QuoteInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function create(CartInterface $cart)
    {
        /** @var Quote $monitoringQuote */
        $monitoringQuote = $this->quoteFactory->create();

        $monitoringQuote
            ->setQuoteId($cart->getId())
            ->setStoreId($cart->getStoreId())
            ->setReservedOrderId($cart->getReservedOrderId());

        return $this->quoteRepository->save($monitoringQuote);
    }

    /**
     * @param QuoteInterface $quote
     *
     * @throws CouldNotSaveException
     */
    public function save(QuoteInterface $quote)
    {
        $this->quoteRepository->save($quote);
    }

    /**
     * @param QuoteInterface $quote
     *
     * @return QuoteInterface|Quote
     * @throws NoSuchEntityException
     */
    public function reload(QuoteInterface $quote)
    {
        $quote = $this->quoteRepository->load($quote->getEntityId());
        return $quote;
    }
}
