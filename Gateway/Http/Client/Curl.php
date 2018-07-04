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
namespace Vipps\Payment\Gateway\Http\Client;

use Magento\Framework\{
    HTTP\Adapter\Curl as MagentoCurl, 
    HTTP\Adapter\CurlFactory, Json\EncoderInterface
};
use Magento\Payment\Gateway\{ConfigInterface, Http\TransferInterface};
use Vipps\Payment\Gateway\Exception\AuthenticationException;
use Vipps\Payment\Model\TokenProviderInterface;
use Zend\Http\{Request, Response as ZendResponse};
use Psr\Log\LoggerInterface;

/**
 * Class Curl
 * @package Vipps\Payment\Gateway\Http\Client
 * @SuppressWarnings(PHPMD.UnusedPrivateField)
 */
class Curl implements ClientInterface
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
     * @var TokenProviderInterface
     */
    private $tokenProvider;

    /**
     * @var
     */
    private $jsonEncoder;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Curl constructor.
     *
     * @param ConfigInterface $config
     * @param CurlFactory $adapterFactory
     * @param TokenProviderInterface $tokenProvider
     * @param EncoderInterface $jsonEncoder
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConfigInterface $config,
        CurlFactory $adapterFactory,
        TokenProviderInterface $tokenProvider,
        EncoderInterface $jsonEncoder,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->adapterFactory = $adapterFactory;
        $this->tokenProvider = $tokenProvider;
        $this->jsonEncoder = $jsonEncoder;
        $this->logger = $logger;
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
            $adapter = null;
            /** @var MagentoCurl $adapter */
            $adapter = $this->adapterFactory->create();
            $options = $this->getBasicOptions();

            if ($transfer->getMethod() === Request::METHOD_PUT) {
                $options = $options +
                    [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_CUSTOMREQUEST => Request::METHOD_PUT,
                        CURLOPT_POSTFIELDS => $this->jsonEncoder->encode($transfer->getBody())
                    ];
            }
            $adapter->setOptions($options);

            // send request
            $adapter->write(
                $transfer->getMethod(),
                $transfer->getUri(),
                '1.1',
                $this->getHeaders($transfer->getHeaders()),
                $this->jsonEncoder->encode($transfer->getBody())
            );

            $responseSting = $adapter->read();
            $response = ZendResponse::fromString($responseSting);
            return ['response' => $response];
        } catch (\Throwable $t) {
            $this->logger->critical($t->__toString());
            throw new \Exception($t->getMessage(), $t->getCode(), $t); //@codingStandardsIgnoreLine
        } finally {
            $adapter ? $adapter->close() : null;
        }
    }

    /**
     * @param $headers
     *
     * @return array
     * @throws AuthenticationException
     */
    private function getHeaders($headers)
    {
        $headers = array_merge(
            [
                self::HEADER_PARAM_CONTENT_TYPE => 'application/json',
                self::HEADER_PARAM_AUTHORIZATION => 'Bearer ' . $this->tokenProvider->get(),
                self::HEADER_PARAM_X_REQUEST_ID => '',
                self::HEADER_PARAM_X_SOURCE_ADDRESS => '',
                self::HEADER_PARAM_X_TIMESTAMP => '',
                self::HEADER_PARAM_SUBSCRIPTION_KEY => $this->config->getValue('subscription_key2')
            ],
            $headers
        );

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
