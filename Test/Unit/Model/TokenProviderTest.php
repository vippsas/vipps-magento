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

namespace Vipps\Payment\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ScopeInterface;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\HTTP\Adapter\Curl;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Vipps\Payment\GatewayEpayment\Config\Config;
use Vipps\Payment\GatewayEpayment\Exception\AuthenticationException;
use Vipps\Payment\Model\TokenProvider;
use Vipps\Payment\Model\UrlResolver;

/**
 * Every call to Vipps/MobilePay carries this access token, so a stored one is reused while it lasts and a
 * fresh one is fetched, and stored, shortly before it runs out.
 *
 * @package Vipps\Payment\Test\Unit\Model
 */
class TokenProviderTest extends TestCase
{
    private const STORE_ID = 1;

    private AdapterInterface|MockObject $connection;
    private CurlFactory|MockObject $curlFactory;

    /** @var array<string, mixed>|false the stored token row */
    private array|false $storedRow = false;

    /** @var string raw HTTP response from the access token endpoint */
    private string $httpResponse = '';

    protected function setUp(): void
    {
        $select = $this->createMock(Select::class);
        foreach (['from', 'where', 'limit', 'order'] as $method) {
            $select->method($method)->willReturnSelf();
        }

        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);
        $this->connection->method('fetchRow')->willReturnCallback(fn () => $this->storedRow);

        $this->curlFactory = $this->createMock(CurlFactory::class);
    }

    public function testReusesAStoredTokenThatIsStillValid(): void
    {
        $this->storedRow = ['token_id' => 5, 'access_token' => 'stored', 'expires_on' => time() + 3600];
        $this->curlFactory->expects($this->never())->method('create');

        $this->assertSame('stored', $this->provider()->get(self::STORE_ID));
    }

    /**
     * A token about to run out could expire while the request that carries it is still in flight.
     */
    public function testReplacesAStoredTokenThatIsAboutToExpire(): void
    {
        $this->storedRow = ['token_id' => 5, 'access_token' => 'stored', 'expires_on' => time() + 30];
        $this->respondWith(200, $this->jwt('fresh'));

        $this->connection->expects($this->once())->method('update')
            ->with('vipps_payment_jwt', $this->callback(fn ($row) => $row['access_token'] === 'fresh'), 'token_id = 5');
        $this->connection->expects($this->never())->method('insert');

        $this->assertSame('fresh', $this->provider()->get(self::STORE_ID));
    }

    public function testStoresTheFirstTokenForTheStore(): void
    {
        $this->respondWith(200, $this->jwt('fresh'));

        $this->connection->expects($this->once())->method('insert')
            ->with('vipps_payment_jwt', $this->callback(
                fn ($row) => $row['access_token'] === 'fresh'
                    && $row['scope'] === 'store'
                    && $row['scope_id'] === self::STORE_ID
            ));

        $this->assertSame('fresh', $this->provider()->get(self::STORE_ID));
    }

    public function testARejectedRequestIsAnAuthenticationError(): void
    {
        $this->respondWith(401, ['error' => 'unauthorized_client']);

        $this->expectException(AuthenticationException::class);
        $this->provider()->get(self::STORE_ID);
    }

    public function testAnIncompleteTokenIsAnAuthenticationError(): void
    {
        $this->respondWith(200, ['access_token' => 'fresh']);

        $this->expectException(AuthenticationException::class);
        $this->provider()->get(self::STORE_ID);
    }

    public function testATokenThatCannotBeStoredIsReported(): void
    {
        $this->respondWith(200, $this->jwt('fresh'));
        $this->connection->method('insert')->willThrowException(new \RuntimeException('read-only'));

        $this->expectException(CouldNotSaveException::class);
        $this->provider()->get(self::STORE_ID);
    }

    /**
     * @return array<string, mixed>
     */
    private function jwt(string $accessToken): array
    {
        return [
            'token_type' => 'Bearer',
            'expires_in' => '86398',
            'ext_expires_in' => '86398',
            'expires_on' => (string)(time() + 86398),
            'not_before' => (string)time(),
            'resource' => '00000002-0000-0000-c000-000000000000',
            'access_token' => $accessToken,
        ];
    }

    private function respondWith(int $status, array $body): void
    {
        $reason = $status === 200 ? 'OK' : 'Unauthorized';
        $this->httpResponse = "HTTP/1.1 {$status} {$reason}\r\nContent-Type: application/json\r\n\r\n"
            . json_encode($body);

        $curl = $this->createMock(Curl::class);
        $curl->method('read')->willReturnCallback(fn () => $this->httpResponse);
        $this->curlFactory->method('create')->willReturn($curl);
    }

    private function provider(): TokenProvider
    {
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        $config = $this->createMock(Config::class);
        $config->method('getValue')->willReturn('credential');
        $config->method('getTitle')->willReturn('Vipps');

        $urlResolver = $this->createMock(UrlResolver::class);
        $urlResolver->method('getUrl')->willReturn('https://apitest.vipps.no/accessToken/get');

        $scope = $this->createMock(ScopeInterface::class);
        $scope->method('getId')->willReturn(self::STORE_ID);
        $scopeResolver = $this->createMock(ScopeResolverInterface::class);
        $scopeResolver->method('getScope')->willReturn($scope);

        return new TokenProvider(
            $resourceConnection,
            $this->curlFactory,
            $config,
            new Json(),
            $this->createMock(LoggerInterface::class),
            $urlResolver,
            $scopeResolver
        );
    }
}
