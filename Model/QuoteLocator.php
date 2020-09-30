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

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteRepository;

/**
 * Class QuoteLocator
 * @package Vipps\Payment\Model
 */
class QuoteLocator
{
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * QuoteLocator constructor.
     *
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param QuoteRepository $quoteRepository
     */
    public function __construct(
        SearchCriteriaBuilder $searchCriteriaBuilder,
        QuoteRepository $quoteRepository
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Retrieve a quote by increment id
     *
     * @param string $incrementId
     *
     * @return CartInterface|Quote
     */
    public function get($incrementId)
    {
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('reserved_order_id', $incrementId, 'eq')
            ->create();
        $quoteList = $this->quoteRepository->getList($searchCriteria)->getItems();
        $quote = current($quoteList);
        return $quote ?: null;
    }
}
