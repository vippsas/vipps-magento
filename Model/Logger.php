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

use DateTimeZone;
use Magento\Payment\Gateway\ConfigInterface;
use Monolog\Handler\HandlerInterface;

class Logger extends \Monolog\Logger
{
    /**
     * @param string $name
     * @param ConfigInterface $config
     * @param list<HandlerInterface> $handlers
     * @param callable[] $processors
     * @param DateTimeZone|null $timezone
     */
    public function __construct(
        string $name,
        ConfigInterface $config,
        array $handlers = [],
        array $processors = [],
        ?DateTimeZone $timezone = null
    ) {
        if (!$config->getValue('debug')) {
            unset($handlers['debug']);
        }

        parent::__construct($name, $handlers, $processors, $timezone);
    }
}
