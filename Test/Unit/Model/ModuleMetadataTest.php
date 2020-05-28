<?php
/**
 * Copyright 2018 Vipps
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
namespace Vipps\Payment\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Vipps\Payment\Model\ModuleMetadata;
use Vipps\Payment\Model\ModuleMetadataInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use \Magento\Framework\App\Config;
use \Magento\Framework\Module\ResourceInterface;
use \Magento\Framework\App\CacheInterface;
use \Magento\Framework\App\ProductMetadataInterface;

/**
 * Class ModuleMetadataTest
 * @package Vipps\Payment\Test\Unit\Model
 */
class ModuleMetadataTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $resource;

    /**
     * @var MockObject
     */
    private $cache;

    /**
     * @var MockObject
     */
    private $config;

    /**
     * @var MockObject
     */
    private $metadata;

    /**
     * @var ModuleMetadata
     */
    private $module;

    protected function setUp()
    {
        $this->resource = $this->getMockBuilder(ResourceInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->cache = $this->getMockBuilder(CacheInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->metadata = $this->getMockBuilder(ProductMetadataInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $managerHelper = new ObjectManager($this);
        $this->module = $managerHelper->getObject(ModuleMetadata::class, [
            'resource' => $this->resource,
            'systemMetadata' => $this->metadata,
            'cache' => $this->cache,
        ]);
    }

    public function testAddOptionalHeaders()
    {
        $magentoVersion = '2.3.5';
        $moduleVersion = '2.3.0';
        $systemName = 'Magento';
        $edition = 'Enterprise';
        $this->metadata->expects($this->any())
            ->method('getName')
            ->willReturn($systemName);
        $this->metadata->expects($this->any())
            ->method('getVersion')
            ->willReturn($magentoVersion);
        $this->metadata->expects($this->any())
            ->method('getEdition')
            ->willReturn($edition);
        $this->cache->expects($this->any())
            ->method('load')
            ->willReturn($moduleVersion);
        $this->resource->expects($this->any())
            ->method('getDbVersion')
            ->willReturn($moduleVersion);
        $this->assertEquals(
            [
                'Vipps-System-Name' => $systemName . ' 2 ' . $edition,
                'Vipps-System-Version' => $magentoVersion,
                'Vipps-System-Plugin-Name' => ModuleMetadataInterface::MODULE_NAME,
                'Vipps-System-Plugin-Version' => $moduleVersion,
            ],
            $this->module->addOptionalHeaders([])
        );
    }
}
