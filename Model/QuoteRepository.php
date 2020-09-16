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

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Api\QuoteRepositoryInterface;
use Vipps\Payment\Model\QuoteFactory;
use Vipps\Payment\Model\ResourceModel\Quote as QuoteResource;

/**
 * Class QuoteRepository
 */
class QuoteRepository implements QuoteRepositoryInterface
{
    /**
     * @var QuoteResource
     */
    private $quoteResource;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * QuoteRepository constructor.
     *
     * @param QuoteResource $quoteResource .
     * @param QuoteFactory $quoteFactory
     */
    public function __construct(QuoteResource $quoteResource, QuoteFactory $quoteFactory)
    {
        $this->quoteResource = $quoteResource;
        $this->quoteFactory = $quoteFactory;
    }

    /**
     * Save monitoring record
     *
     * @param QuoteInterface $quote
     * @return QuoteInterface
     * @throws CouldNotSaveException
     */
    public function save(QuoteInterface $quote)
    {
        try {
            $this->quoteResource->save($quote);

            return $quote;
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __(
                    'Could not save Vipps Quote: %1',
                    $e->getMessage()
                ),
                $e
            );
        }
    }

    /**
     * Load monitoring quote by quote.
     *
     * @param $quoteId
     *
     * @return Quote
     * @throws NoSuchEntityException
     */
    public function loadByQuote($quoteId)
    {
        $vippsQuote = $this->quoteFactory->create();
        $this->quoteResource->load($vippsQuote, $quoteId, 'quote_id');

        if (!$vippsQuote->getId()) {
            throw NoSuchEntityException::singleField('quote_id', $quoteId);
        }

        return $vippsQuote;
    }

    /**
     * @param string $orderId
     *
     * @return QuoteInterface
     * @throws NoSuchEntityException
     */
    public function loadByOrderId($orderId)
    {
        $vippsQuote = $this->quoteFactory->create();
        $this->quoteResource->load($vippsQuote, $orderId, 'reserved_order_id');

        if (!$vippsQuote->getId()) {
            throw NoSuchEntityException::singleField('reserved_order_id', $orderId);
        }

        return $vippsQuote;
    }

    /**
     * @param int $vippsQuoteId
     *
     * @return Quote
     * @throws NoSuchEntityException
     */
    public function load(int $vippsQuoteId)
    {
        $vippsQuote = $this->quoteFactory->create();
        $this->quoteResource->load($vippsQuote, $vippsQuoteId);

        if (!$vippsQuote->getId()) {
            throw NoSuchEntityException::singleField('entity_id', $vippsQuoteId);
        }

        return $vippsQuote;
    }
}
