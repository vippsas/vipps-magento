<?php

/**
 * Copyright 2020 Vipps
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software
 * and associated documentation files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING
 * BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NON
 * INFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
 * DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace Vipps\Payment\Test\Integration\Model;

use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Vipps\Payment\Model\Logger;
use Vipps\Payment\Model\Logger\Handler\Debug;
use Vipps\Payment\Model\Logger\Handler\Error;

use function get_class;
use function in_array;

class LoggerTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture current_store payment/vipps/debug 0
     */
    public function testConstructWithDebugConfigOffShouldNotIncludeDebugHandler()
    {
        /** @var Logger $logger */
        $logger = $this->objectManager->create(Logger::class);
        $handlers = $this->getLoggerHandlerClasses($logger);
        $this->assertTrue(in_array(Error::class, $handlers));
        $this->assertFalse(in_array(Debug::class, $handlers));
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture current_store payment/vipps/debug 1
     */
    public function testConstructWithDebugConfigOnShouldIncludeDebugHandler()
    {
        /** @var Logger $logger */
        $logger = $this->objectManager->create(Logger::class);
        $handlers = $this->getLoggerHandlerClasses($logger);
        $this->assertTrue(in_array(Error::class, $handlers));
        $this->assertTrue(in_array(Debug::class, $handlers));
    }

    /**
     * @param Logger $logger
     *
     * @return string[]
     */
    private function getLoggerHandlerClasses(Logger $logger): array
    {
        $classes = [];
        $handlers = $logger->getHandlers();
        foreach ($handlers as $handler) {
            $classes[] = get_class($handler);
        }

        return $classes;
    }
}
