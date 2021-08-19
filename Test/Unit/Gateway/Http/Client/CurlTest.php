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

namespace Vipps\Payment\Test\Gateway\Http\Client;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\HTTP\Adapter\Curl;
use Magento\Framework\Json\EncoderInterface;
use Vipps\Payment\Gateway\Http\Client\Curl as VippsCurl;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Model\TokenProviderInterface;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use PHPUnit\Framework\TestCase;
use Vipps\Payment\Model\ModuleMetadataInterface;

/**
 * Class CurlTest
 * @package Vipps\Payment\Test\Gateway\Http\Client
 * @SuppressWarnings(PHPMD.UnusedPrivateField)
 */
class CurlTest extends TestCase
{
    /**
     * @var VippsCurl
     */
    private $action;

    /**
     * @var ConfigInterface|MockObject
     */
    private $config;

    /**
     * @var CurlFactory|MockObject
     */
    private $adapterFactory;

    /**
     * @var Curl|MockObject
     */
    private $adapter;

    /**
     * @var TokenProviderInterface|MockObject
     */
    private $tokenProvider;

    /**
     * @var EncoderInterface|MockObject
     */
    private $jsonEncoder;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var ObjectManager
     */
    private $objectManagerHelper;

    /**
     * @var ModuleMetadataInterface
     */
    private $moduleMetadata;

    protected function setUp(): void
    {
        $this->config = $this->getMockBuilder(ConfigInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['getPaymentDetails'])
            ->getMockForAbstractClass();
        $this->adapterFactory = $this->getMockBuilder(\Magento\Framework\HTTP\Adapter\CurlFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();
        $this->adapter = $this->getMockBuilder(Curl::class)
            ->disableOriginalConstructor()
            ->setMethods(['read'])
            ->getMock();
        $this->adapterFactory->method('create')
            ->willReturn($this->adapter);
        $this->tokenProvider = $this->getMockBuilder(TokenProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->jsonEncoder = $this->getMockBuilder(EncoderInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->moduleMetadata = $this->getMockBuilder(ModuleMetadataInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->objectManagerHelper = new ObjectManager($this);
        $this->action = $this->objectManagerHelper->getObject(VippsCurl::class, [
            'config' => $this->config,
            'adapterFactory' => $this->adapterFactory,
            'tokenProvider' => $this->tokenProvider,
            'jsonEncoder' => $this->jsonEncoder,
            'logger' => $this->logger,
            'moduleMetadata' => $this->moduleMetadata,
        ]);
    }

    /**
     * @param $str
     * @param $isExceptionExpected
     * @dataProvider dataProvider
     * @throws \Exception
     */
    public function testPlaceRequest($str, $isExceptionExpected)
    {
        $this->adapter->expects($this->once())
            ->method('read')
            ->willReturn($str);
        if ($isExceptionExpected) {
            $this->expectException(\Exception::class);
        }
        /** @var  TransferInterface|MockObject $transfer */
        $transfer = $this->getMockBuilder(TransferInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['getHeaders'])
            ->getMockForAbstractClass();
        $transfer->expects($this->once())
            ->method('getHeaders')
            ->willReturn([]);
        $this->moduleMetadata->expects($this->atLeastOnce())
            ->method('addOptionalHeaders')
            ->willReturn([]);
        $this->action->placeRequest($transfer);
    }

    public function dataProvider()
    {
        $str = 'HTTP/1.1 200 OK
              Content-Type: application/json;charset=UTF-8
              X-Application-Context: dwo-payment:mt1:8443
              Date: Fri, 11 May 2018 15:08:16 GMT
             {"orderId":"0000000","url":"https://apitest.vipps.no/mt1/
              dwo-api-application/v1/deeplink/vippsgateway?token=test"}';
        return [
            [$str, false],
            ['', true],
        ];
    }
}
