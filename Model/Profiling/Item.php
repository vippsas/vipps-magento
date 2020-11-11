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
namespace Vipps\Payment\Model\Profiling;

use Magento\Framework\Model\AbstractModel;
use Vipps\Payment\Api\Profiling\Data\ItemInterface;
use Vipps\Payment\Model\ResourceModel\Profiling\Item as ProfilingItemResource;

/**
 * Class Item
 * @package Vipps\Payment\Model\Profiling
 */
class Item extends AbstractModel implements ItemInterface
{
    /**
     * {@inheritdoc}
     */
    protected function _construct() //@codingStandardsIgnoreLine
    {
        $this->_init(ProfilingItemResource::class);
    }

    /**
     * Return date when item was created
     *
     * @return string
     */
    public function getCreatedAt(): string
    {
        return (string)$this->getData(self::CREATED_AT);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setCreatedAt($value)
    {
        return $this->setData(self::CREATED_AT, $value);
    }

    /**
     * Return increment id value
     *
     * @return string|null
     */
    public function getEntityId()
    {
        return $this->getId();
    }

    /**
     * Set increment id value
     *
     * @param string $value
     */
    public function setEntityId($value)
    {
        $this->setData(self::ENTITY_ID, $value);
    }

    /**
     * Return increment id value
     *
     * @return string
     */
    public function getIncrementId() : string
    {
        return (string)$this->getData(self::INCREMENT_ID);
    }

    /**
     * Set increment id value
     *
     * @param string $value
     */
    public function setIncrementId($value)
    {
        $this->setData(self::INCREMENT_ID, $value);
    }

    /**
     * Return increment id value
     *
     * @return string
     */
    public function getStatusCode() : string
    {
        return (string)$this->getData(self::STATUS_CODE);
    }

    /**
     * Set increment id value
     *
     * @param string $value
     */
    public function setStatusCode($value)
    {
        $this->setData(self::STATUS_CODE, $value);
    }

    /**
     * Return request type value
     *
     * @return string
     */
    public function getRequestType() : string
    {
        return (string)$this->getData(self::REQUEST_TYPE);
    }

    /**
     * Set request type value
     *
     * @param string $value
     */
    public function setRequestType($value)
    {
        $this->setData(self::REQUEST_TYPE, $value);
    }

    /**
     * Return request value
     *
     * @return string
     */
    public function getRequest(): string
    {
        return (string)$this->getData(self::REQUEST);
    }

    /**
     * Set request value
     *
     * @param string $value
     */
    public function setRequest($value)
    {
        $this->setData(self::REQUEST, $value);
    }

    /**
     * Return formatted request value
     *
     * @return string
     */
    public function getFormattedRequest(): string
    {
        return $this->formatHtml($this->getData(self::REQUEST));
    }

    /**
     * Return response value
     *
     * @return string
     */
    public function getResponse(): string
    {
        return (string)$this->getData(self::RESPONSE);
    }

    /**
     * Set response value
     *
     * @param string $value
     */
    public function setResponse($value)
    {
        $this->setData(self::RESPONSE, $value);
    }

    /**
     * Return formatted response value
     *
     * @return string
     */
    public function getFormattedResponse(): string
    {
        return $this->formatHtml($this->getData(self::RESPONSE));
    }

    /**
     * Return formatted value, replace map is:
     *  - whitespace to '$nbsp; '
     *  - apply 'nl2br'
     *  - apply 'htmlspecialchars'
     *
     * @param $value
     * @return string
     */
    private function formatHtml($value): string
    {
        return nl2br(str_replace(' ', '&nbsp; ', htmlspecialchars($value)));
    }
}
