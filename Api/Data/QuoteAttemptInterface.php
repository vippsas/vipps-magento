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

namespace Vipps\Payment\Api\Data;

/**
 * Interface QuoteInterface
 * @api
 */
interface QuoteAttemptInterface
{
    /**
     * @const string
     */
    const ENTITY_ID = 'entity_id';

    /**
     * @const string Vipps Quote Id.
     */
    const PARENT_ID = 'parent_id';

    /**
     * @const string
     */
    const MESSAGE = 'message';

    /**
     * @const string
     */
    const CREATED_AT = 'created_at';

    /**
     * @param int $parentId
     * @return self
     */
    public function setParentId(int $parentId);

    /**
     * @param string $message
     * @return string
     */
    public function setMessage(string $message);

    /**
     * @param string $createdAt
     * @return string
     */
    public function setCreatedAt(string $createdAt);

    /**
     * @return int
     */
    public function getParentId();

    /**
     * @return int
     */
    public function getEntityId();

    /**
     * @return string
     */
    public function getMessage();

    /**
     * @return string
     */
    public function getCreatedAt();
}
