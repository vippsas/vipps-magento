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

namespace Vipps\Payment\Model\Quote;

use Magento\Framework\Exception\CouldNotSaveException;
use Vipps\Payment\Api\Data\QuoteAttemptInterface;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Api\Quote\AttemptRepositoryInterface;
use Vipps\Payment\Model\ResourceModel\Quote\Attempt as AttemptResource;
use Vipps\Payment\Model\ResourceModel\Quote\Attempt\CollectionFactory;

/**
 * Class AttemptRepository
 */
class AttemptRepository implements AttemptRepositoryInterface
{
    /**
     * @var AttemptResource
     */
    private $resource;
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @param AttemptResource $resource
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(AttemptResource $resource, CollectionFactory $collectionFactory)
    {
        $this->resource = $resource;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @param QuoteAttemptInterface $attempt
     * @return QuoteAttemptInterface
     * @throws CouldNotSaveException
     */
    public function save(QuoteAttemptInterface $attempt)
    {
        try {
            $this->resource->save($attempt);

            return $attempt;
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __(
                    'Could not save Vipps Quote Attempt: %1',
                    $e->getMessage()
                ),
                $e
            );
        }
    }

    /**
     * @param QuoteInterface $quote
     * @return AttemptResource\Collection
     */
    public function getByVippsQuote(QuoteInterface $quote)
    {
        /** @var \Vipps\Payment\Model\ResourceModel\Quote\Attempt\Collection $collection */
        $collection = $this->collectionFactory->create();
        $collection
            ->addFieldToFilter('parent_id', ['eq' => $quote->getEntityId()])
            ->setOrder('created_at');

        return $collection;
    }
}
