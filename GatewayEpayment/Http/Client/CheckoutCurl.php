<?php
/**
 * Copyright 2022 Vipps
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
namespace Vipps\Payment\GatewayEpayment\Http\Client;

use Magento\Framework\HTTP\Adapter\Curl as MagentoCurl;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Magento\Framework\Json\EncoderInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Model\ModuleMetadataInterface;

/**
 * Class CheckoutCurl
 * @package Vipps\Payment\GatewayEpayment\Http\Client
 * @SuppressWarnings(PHPMD.UnusedPrivateField)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CheckoutCurl implements ClientInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var CurlFactory
     */
    private $adapterFactory;

    /**
     * @var
     */
    private $jsonEncoder;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ModuleMetadataInterface
     */
    private $moduleMetadata;

    /**
     * CheckoutCurl constructor.
     *
     * @param ConfigInterface $config
     * @param CurlFactory $adapterFactory
     * @param EncoderInterface $jsonEncoder
     * @param LoggerInterface $logger
     * @param ModuleMetadataInterface $moduleMetadata
     */
    public function __construct(
        ConfigInterface $config,
        CurlFactory $adapterFactory,
        EncoderInterface $jsonEncoder,
        LoggerInterface $logger,
        ModuleMetadataInterface $moduleMetadata
    ) {
        $this->config = $config;
        $this->adapterFactory = $adapterFactory;
        $this->jsonEncoder = $jsonEncoder;
        $this->logger = $logger;
        $this->moduleMetadata = $moduleMetadata;
    }

    /**
     * @param TransferInterface $transfer
     *
     * @return array|string
     * @throws \Exception
     */
    public function placeRequest(TransferInterface $transfer)
    {
        try {
            $response = $this->place($transfer);

            return ['response' => $response];
        } catch (\Throwable $t) {
            $this->logger->critical($t->__toString());
            throw new \Exception($t->getMessage(), $t->getCode(), $t); //@codingStandardsIgnoreLine
        }
    }

    /**
     * @param TransferInterface $transfer
     *
     * @return Response
     */
    private function place(TransferInterface $transfer)
    {
        try {
            $adapter = null;
            /** @var MagentoCurl $adapter */
            $adapter = $this->adapterFactory->create();
            $options = $this->getBasicOptions();
            $requestBody = $transfer->getBody();
            unset($requestBody['payment']);
            if ($transfer->getMethod() === Request::METHOD_PUT) {
                $options += [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST => Request::METHOD_PUT,
                    CURLOPT_POSTFIELDS => $this->jsonEncoder->encode($requestBody)
                ];
            }
            $adapter->setOptions($options);
            $headers = $this->getHeaders($transfer->getHeaders());
            // send request
            $adapter->write(
                $transfer->getMethod(),
                $transfer->getUri(),
                '1.1',
                $headers,
                $this->jsonEncoder->encode($transfer->getBody())
            );

            $response = $adapter->read();

            return Response::fromString($response);
        } finally {
            $adapter ? $adapter->close() : null;
        }
    }

    /**
     * @param $headers
     *
     * @return array
     */
    private function getHeaders($headers)
    {
        $headers = array_merge(
            [
                self::HEADER_PARAM_CONTENT_TYPE => 'application/json',
                self::HEADER_PARAM_IDEMPOTENCY_KEY => '',
                self::HEADER_PARAM_X_SOURCE_ADDRESS => '',
                self::HEADER_PARAM_X_TIMESTAMP => '',
                self::HEADER_PARAM_MERCHANT_SERIAL_NUMBER => $this->config->getValue('merchant_serial_number'),
                self::HEADER_PARAM_CLIENT_ID => $this->config->getValue('client_id'),
                self::HEADER_PARAM_CLIENT_SECRET => $this->config->getValue('client_secret'),
                self::HEADER_PARAM_SUBSCRIPTION_KEY => $this->config->getValue('subscription_key1'),
            ],
            $headers
        );

        $headers = $this->moduleMetadata->addOptionalHeaders($headers);

        $result = [];
        foreach ($headers as $key => $value) {
            $result[] = sprintf('%s: %s', $key, $value);
        }

        return $result;
    }

    /**
     * @return array
     */
    private function getBasicOptions()
    {
        return [
            CURLOPT_TIMEOUT => 30,
        ];
    }
}
