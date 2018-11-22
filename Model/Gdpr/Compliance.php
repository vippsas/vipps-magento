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

namespace Vipps\Payment\Model\Gdpr;

use Magento\Framework\Exception\SerializationException;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class Compliance
{
    /**
     * @var Json
     */
    private $serializer;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(Json $serializer, LoggerInterface $logger)
    {
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    /**
     * Fields that require masking.
     *
     * @return array
     */
    private function getReplacementSchema(): array
    {
        $schema = [
            'addressLine1' => 1,
            'addressLine2' => 1,
            'email' => 1,
            'firstName' => 1,
            'lastName' => 1,
            'mobileNumber' => 1,
        ];

        return $schema;
    }

    /**
     * Mask response fields.
     *
     * @param array|string $responseData
     *
     * @return array|string
     */
    public function process($responseData)
    {
        $wasPacked = false;

        try {
            if (\is_string($responseData)) {
                $responseData = $this->serializer->unserialize($responseData);
                $wasPacked = true;
            }

            if (!\is_array($responseData)) {
                throw new SerializationException(__('Unserialization result is not an array'));
            }

            array_walk_recursive($responseData, function (&$item, $key, $schema) {
                if (isset($schema[$key])) {
                    $item = str_repeat('x', \strlen($item));
                }
            }, $this->getReplacementSchema());

            if ($wasPacked) {
                $responseData = $this->serializer->serialize($responseData);
            }
        } catch (\Exception $e) {
            $this->logger->critical('Gdpr compliance failed');
            $this->logger->critical($e->getMessage());
        }

        return $responseData;
    }
}
