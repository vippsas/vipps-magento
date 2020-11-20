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
namespace Vipps\Payment\Api\Profiling\Data;

/**
 * Interface ItemInterface
 * @package Vipps\Payment\Api\Profiling\Data
 */
interface ItemInterface
{
    /**
     * @var string
     */
    const ENTITY_ID = 'entity_id';

    /**
     * @var string
     */
    const INCREMENT_ID = 'increment_id';

    /**
     * @var string
     */
    const STATUS_CODE = 'status_code';

    /**
     * @var string
     */
    const REQUEST_TYPE = 'request_type';

    /**
     * @var string
     */
    const REQUEST = 'request';

    /**
     * @var string
     */
    const RESPONSE = 'response';

    /**
     * @var string
     */
    const CREATED_AT = 'created_at';

    /**
     * @return string
     */
    public function getEntityId();

    /**
     * @param string $value
     *
     * @return null
     */
    public function setEntityId($value);

    /**
     * @return string
     */
    public function getIncrementId();

    /**
     * @param string $value
     *
     * @return null
     */
    public function setIncrementId($value);

    /**
     * @return string
     */
    public function getStatusCode();

    /**
     * @param string $value
     *
     * @return null
     */
    public function setStatusCode($value);

    /**
     * @return string
     */
    public function getRequestType();

    /**
     * @param string $value
     *
     * @return null
     */
    public function setRequestType($value);

    /**
     * @return string
     */
    public function getRequest();

    /**
     * @param string $value
     *
     * @return null
     */
    public function setRequest($value);

    /**
     * @return string
     */
    public function getResponse();

    /**
     * @param string $value
     *
     * @return null
     */
    public function setResponse($value);

    /**
     * @return string
     */
    public function getCreatedAt();

    /**
     * @param string $value
     * @return $this
     */
    public function setCreatedAt($value);
}
