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

namespace Vipps\Payment\Model\Monitoring\Quote;

use Magento\Framework\Model\AbstractModel;
use Vipps\Payment\Api\Monitoring\Data\QuoteCancellationInterface;
use Vipps\Payment\Model\ResourceModel\Monitoring\Quote\Cancellation as CancellationResource;

/**
 * Quote cancellation model.
 */
class Cancellation extends AbstractModel implements QuoteCancellationInterface
{
    /**
     * @return int
     */
    public function getParentId()
    {
        return $this->getData(self::PARENT_ID);
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->getData(self::TYPE);
    }

    /**
     * @return string
     */
    public function getPhrase()
    {
        return $this->getData(self::PHRASE);
    }

    /**
     * @param int $parentId
     * @return self
     */
    public function setParentId(int $parentId)
    {
        return $this->setData($parentId, $parentId);
    }

    /**
     * @param string $type
     * @return self
     */
    public function setType(string $type)
    {
        $this->setData(self::TYPE, $type);
    }

    /**
     * @param string $phrase
     * @return self
     */
    public function setPhrase(string $phrase)
    {
        return $this->setData(self::PHRASE, $phrase);
    }

    /**
     * Constructor.
     */
    protected function _construct()
    {
        $this->_init(CancellationResource::class);
    }
}
