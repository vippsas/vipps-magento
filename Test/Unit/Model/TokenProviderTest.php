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

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\{
    App\ResourceConnection, App\ScopeInterface, DB\Adapter\AdapterInterface, DB\Select, HTTP\ZendClientFactory,
    HTTP\ZendClient, TestFramework\Unit\Helper\ObjectManager, Serialize\SerializerInterface
};
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Gateway\Exception\AuthenticationException;
use Vipps\Payment\Model\TokenProvider;
use Vipps\Payment\Model\UrlResolver;
use Zend_Http_Response;
/**
 * Class TokenProvider
 * @package Vipps\Payment\Test\Unit\Model
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class TokenProviderTest extends TestCase
{
    /**
     * @var TokenProvider
     */
    private $action;

    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var ZendClient|MockObject
     */
    private $httpClient;

    /**
     * @var ZendClientFactory|MockObject
     */
    private $httpClientFactory;

    /**
     * @var Zend_Http_Response|MockObject
     */
    private $httpClientResponse;

    /**
     * @var ResourceConnection|MockObject
     */
    private $resourceConnection;

    /**
     * @var ConfigInterface|MockObject
     */
    private $config;

    /**
     * @var Select|MockObject
     */
    private $select;

    /**
     * @var UrlResolver|MockObject
     */
    private $urlResolver;

    /**
     * @var SerializerInterface|MockObject
     */
    private $serializer;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var ScopeInterface|MockObject
     */
    private $scope;

    /**
     * @var ScopeResolverInterface|MockObject
     */
    private $scopeResolver;

    protected function setUp()
    {
        $this->resourceConnection = $this->getMockBuilder(ResourceConnection::class)
            ->disableOriginalConstructor()
            ->setMethods(['getConnection', 'getTableName'])
            ->getMock();
        $this->connection = $this->getMockBuilder(AdapterInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['select', 'fetchRow', 'insert', 'update'])
            ->getMockForAbstractClass();
        $this->select = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->select->expects($this->any())->method('from')->will($this->returnSelf());
        $this->select->expects($this->any())->method('where')->will($this->returnSelf());
        $this->select->expects($this->any())->method('limit')->will($this->returnSelf());
        $this->select->expects($this->any())->method('order')->will($this->returnSelf());

        $this->httpClientFactory = $this->getMockBuilder(ZendClientFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();
        $this->httpClient = $this->getMockBuilder(ZendClient::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->httpClient->expects($this->any())->method('setConfig')->will($this->returnSelf());
        $this->httpClient->expects($this->any())->method('setUri')->will($this->returnSelf());
        $this->httpClient->expects($this->any())->method('setMethod')->will($this->returnSelf());
        $this->httpClient->expects($this->any())->method('setHeaders')->will($this->returnSelf());

        $this->httpClientResponse = $this->getMockBuilder(Zend_Http_Response::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->config = $this->getMockBuilder(ConfigInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();

        $this->serializer = $this->getMockBuilder(\Magento\Framework\Serialize\Serializer\Json::class)
            ->disableOriginalConstructor()
            ->setMethods(['unserialize'])
            ->getMock();

        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['critical', 'debug'])
            ->getMockForAbstractClass();

        $this->urlResolver = $this->getMockBuilder(UrlResolver::class)
            ->disableOriginalConstructor()
            ->setMethods(['getUrl'])
            ->getMock();

        $this->scopeResolver = $this->getMockBuilder(ScopeResolverInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['getScope'])
            ->getMockForAbstractClass();
        $this->scope = $this->getMockBuilder(ScopeInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $managerHelper = new ObjectManager($this);
        $this->action = $managerHelper->getObject(TokenProvider::class, [
            'resourceConnection' => $this->resourceConnection,
            'httpClientFactory' => $this->httpClientFactory,
            'config' => $this->config,
            'serializer' => $this->serializer,
            'logger' => $this->logger,
            'urlResolver' => $this->urlResolver,
            'scopeResolver' => $this->scopeResolver
        ]);
    }

    public function testGetWithValidTokenRecord()
    {
        $jwtRecord = [
            "token_id" => "2",
            "scope_id" => "1",
            "token_type" => "Bearer",
            "expires_in" => "86398",
            "ext_expires_in" => "0",
            "expires_on" => "1926390711",
            "not_before" => "1526304012",
            "resource" => "00000002-0000-0000-c000-000000000000",
            "access_token" => "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsIng1dCI6ImlCakwxUmNx"
        ];

        $this->scopeResolver->expects($this->any())
            ->method('getScope')
            ->willReturn($this->scope);

        $this->connection->expects(self::once())
            ->method('select')
            ->willReturn($this->select);

        $this->resourceConnection->expects(self::once())
            ->method('getConnection')
            ->willReturn($this->connection);

        $this->resourceConnection->expects(self::once())
            ->method('getTableName')
            ->willReturn('vipps_payment_jwt');

        $this->connection->expects($this->once())
            ->method('fetchRow')
            ->willReturn($jwtRecord);

        self::assertEquals($this->action->get(), $jwtRecord['access_token']);
    }

    public function testGetWithoutValidTokenRecord()
    {
        $jwtRecord = [];
        $jwtRecordRequested = [
            "scope_id" => "1",
            "token_type" => "Bearer",
            "expires_in" => "86398",
            "ext_expires_in" => "0",
            "expires_on" => "1926390711",
            "not_before" => "1526304012",
            "resource" => "00000002-0000-0000-c000-000000000000",
            "access_token" => "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsIng1dCI6ImlCakwxUmNx"
        ];

        $this->httpClientFactory->expects(self::once())
            ->method('create')
            ->willReturn($this->httpClient);

        $this->scopeResolver->expects($this->any())
            ->method('getScope')
            ->willReturn($this->scope);

        $this->resourceConnection->expects($this->any())
            ->method('getConnection')
            ->willReturn($this->connection);

        $this->resourceConnection->expects(self::any())
            ->method('getTableName')
            ->willReturn('vipps_payment_jwt');

        $this->connection->expects($this->once())
            ->method('select')
            ->willReturn($this->select);

        $this->connection->expects($this->once())
            ->method('fetchRow')
            ->willReturn($jwtRecord);

        $this->connection->expects($this->once())
            ->method('insert')
            ->willReturnSelf();

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($this->httpClientResponse);

        $this->httpClientResponse->expects($this->once())
            ->method('getBody')
            ->willReturn('');

        $this->httpClientResponse->expects($this->once())
            ->method('isSuccessful')
            ->willReturn(true);

        $this->serializer->expects($this->once())
            ->method('unserialize')
            ->willReturn($jwtRecordRequested);

        self::assertEquals($this->action->get(), $jwtRecordRequested['access_token']);
    }

    public function testGetCouldNotSaveException()
    {
        $exception = new \Exception();
        $this->httpClient->method('request')
            ->willThrowException($exception);

        $this->httpClientFactory->expects(self::once())
            ->method('create')
            ->willReturn($this->httpClient);

        $this->resourceConnection->expects($this->once())
            ->method('getConnection')
            ->willReturn($this->connection);

        $this->connection->expects($this->once())
            ->method('select')
            ->willReturn($this->select);

        $this->scopeResolver->expects($this->any())
            ->method('getScope')
            ->willReturn($this->scope);

        $this->serializer->expects($this->never())
            ->method('unserialize');

        $this->expectException(AuthenticationException::class);
        $this->action->get();
    }
}
