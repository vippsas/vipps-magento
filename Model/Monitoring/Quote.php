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
namespace Vipps\Payment\Model\Monitoring;

use Magento\Framework\Model\AbstractModel;
use Vipps\Payment\Api\Monitoring\Data\QuoteInterface;
use Vipps\Payment\Model\ResourceModel\Monitoring\Quote as QuoteResource;

/**
 * Quote monitoring model.
 */
class Quote extends AbstractModel implements QuoteInterface
{
    /**
     * Constructor.
     */
    protected function _construct()
    {
        $this->_init(QuoteResource::class);
    }

    /**
     * @param int $quoteId
     * @return self
     */
    public function setQuoteId(int $quoteId)
    {
        return $this->setData(self::QUOTE_ID, $quoteId);
    }

    /**
     * @param string $reservedOrderId
     * @return self
     */
    public function setReservedOrderId(string $reservedOrderId)
    {
        return $this->setData(self::RESERVED_ORDER_ID, $reservedOrderId);
    }

    /**
     * @return int
     */
    public function getQuoteId()
    {
        return $this->getData(self::QUOTE_ID);
    }

    /**
     * @return string
     */
    public function getReservedOrderId()
    {
        return $this->getData(self::RESERVED_ORDER_ID);
    }
}
