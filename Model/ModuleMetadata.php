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
declare(strict_types=1);

namespace Vipps\Payment\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Metadata
 * @package Vipps\Payment\Model
 */
class ModuleMetadata implements ModuleMetadataInterface
{
    /**
     * Magento version cache key
     */
    const VERSION_CACHE_KEY = 'vipps-magento';

    /**
     * Product version
     *
     * @var string
     */
    private $version;

    /**
     * @var ComponentRegistrar
     */
    private $componentRegistrar;

    /**
     * @var ReadFactory
     */
    private $readFactory;

    /**
     * ProductMetadataInterface
     */
    private $systemMetadata;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Metadata constructor.
     *
     * @param ResourceInterface $resource
     * @param ProductMetadataInterface $systemMetadata
     * @param CacheInterface $cache
     */
    public function __construct(
        ComponentRegistrarInterface $componentRegistrar,
        ReadFactory $readFactory,
        SerializerInterface $serializer,
        ProductMetadataInterface $systemMetadata,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->componentRegistrar = $componentRegistrar;
        $this->readFactory = $readFactory;
        $this->systemMetadata = $systemMetadata;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function addOptionalHeaders(array $headers): array
    {
        $additionalHeaders = [
            'Vipps-System-Name' => $this->getSystemName(),
            'Vipps-System-Version' => $this->getSystemVersion(),
            'Vipps-System-Plugin-Name' => $this->getModuleName(),
            'Vipps-System-Plugin-Version' => $this->getModuleVersion(),
        ];

        return array_merge($headers, $additionalHeaders);
    }

    /**
     * Get system name, magento in out case.
     *
     * @return string
     */
    private function getSystemName(): string
    {
        return sprintf(
            '%s 2 %s',
            $this->systemMetadata->getName(),
            $this->systemMetadata->getEdition()
        );
    }

    /**
     * Get the system version (eg. 2.3.0, 2.2.1).
     *
     * @return string
     */
    private function getSystemVersion(): string
    {
        return (string) $this->systemMetadata->getVersion();
    }

    /**
     * Get the name of the current module (`vipps-magento`).
     *
     * @return string
     */
    private function getModuleName(): string
    {
        return self::MODULE_NAME;
    }

    /**
     * Get the name of the current module (`vipps-magento`).
     *
     * @return string
     */
    private function getModuleVersion(): string
    {
        if ($this->version) {
            return (string) $this->version;
        }

        if ($this->version) {
            return $this->version;
        }

        $path = $this->componentRegistrar->getPath(
            ComponentRegistrar::MODULE,
            'Vipps_Payment'
        );

        try {
            $directoryRead = $this->readFactory->create($path);
            $composerJsonData = $directoryRead->readFile('composer.json');
            $data = $this->serializer->unserialize($composerJsonData);
            $this->version = $data['version'] ?? 'UNKNOWN';
        } catch (\Throwable $t) {
            $this->logger->error($t);
            $this->version =  'UNKNOWN';
        }
        $this->cache->save($this->version, self::VERSION_CACHE_KEY, [Config::CACHE_TAG]);

        return $this->version;
    }
}
