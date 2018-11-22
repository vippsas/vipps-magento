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

namespace Vipps\Model\Gdpr;

use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class Compliance
{
    const MODIFIER = 'gdprcompliance';

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
     * @return array
     */
    private function getReplacementSchema(): array
    {
        $schema = [
            'addressId' => self::MODIFIER,
            'addressLine1' => self::MODIFIER,
            'addressLine2' => self::MODIFIER,
            'city' => self::MODIFIER,
            'country' => self::MODIFIER,
            'postCode' => self::MODIFIER,
            'shippingDetails' => [
                'address' => [
                    'addressLine1' => self::MODIFIER,
                    'addressLine2' => self::MODIFIER,
                    'city' => self::MODIFIER,
                    'country' => self::MODIFIER,
                    'zipCode' => self::MODIFIER
                ]
            ],
            'userDetails' => [
                'email' => self::MODIFIER,
                'firstName' => self::MODIFIER,
                'lastName' => self::MODIFIER,
                'mobileNumber' => self::MODIFIER,
                'userId' => self::MODIFIER]
        ];

        return $schema;
    }

    public function process($string)
    {
        try {
            $data = $this->serializer->unserialize($string);

            $replacementSchema = $this->getReplacementSchema();

            $data = array_replace_recursive($data, array_intersect_key($data, $replacementSchema));

            $string = $this->serializer->serialize($data);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        return $string;
    }
}