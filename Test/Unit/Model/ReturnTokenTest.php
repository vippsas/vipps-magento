<?php
/**
 * Copyright 2026 Vipps
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

use PHPUnit\Framework\TestCase;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Model\ResourceModel\Quote as QuoteResource;
use Vipps\Payment\Model\ReturnToken;

class ReturnTokenTest extends TestCase
{
    private const TOKEN = 'a3f1c2d4e5b6a7980a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f6071';

    public function testGeneratesUnguessableTokens(): void
    {
        $returnToken = new ReturnToken($this->createMock(QuoteResource::class));

        $token = $returnToken->generate();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertNotSame($token, $returnToken->generate());
    }

    /**
     * @dataProvider tokenProvider
     */
    public function testAcceptsOnlyTheStoredUnexpiredToken(
        ?string $stored,
        ?string $presented,
        int $ageSeconds,
        bool $expected
    ): void {
        $vippsQuote = $this->createMock(QuoteInterface::class);
        $vippsQuote->method('getReturnToken')->willReturn($stored);
        $vippsQuote->method('getCreatedAt')->willReturn(gmdate('Y-m-d H:i:s', time() - $ageSeconds));

        $returnToken = new ReturnToken($this->createMock(QuoteResource::class));

        $this->assertSame($expected, $returnToken->isValid($vippsQuote, $presented));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: int, 3: bool}>
     */
    public static function tokenProvider(): array
    {
        return [
            'the stored token' => [self::TOKEN, self::TOKEN, 60, true],
            'a different token' => [self::TOKEN, strrev(self::TOKEN), 60, false],
            'no token' => [self::TOKEN, '', 60, false],
            'a null token' => [self::TOKEN, null, 60, false],
            // Payments started before the upgrade, and every consumed token, have nothing stored. An empty
            // presented token must not match an empty stored one.
            'nothing stored, nothing presented' => [null, '', 60, false],
            'nothing stored, empty string stored' => ['', '', 60, false],
            'just inside the lifetime' => [self::TOKEN, self::TOKEN, ReturnToken::LIFETIME - 60, true],
            'expired' => [self::TOKEN, self::TOKEN, ReturnToken::LIFETIME + 60, false],
        ];
    }

    /**
     * Only the token column is written, and only while it still holds the token this request validated, so a
     * status or order the callback wrote in the meantime is never overwritten.
     */
    public function testConsumingClearsOnlyThisToken(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('update')
            ->with(
                'vipps_quote',
                ['return_token' => null],
                ['entity_id = ?' => 7, 'return_token = ?' => self::TOKEN]
            )
            ->willReturn(1);
        $vippsQuote = $this->vippsQuote(self::TOKEN);
        $vippsQuote->expects($this->once())->method('setReturnToken')->with(null);

        $this->assertTrue($this->createReturnToken($connection)->consume($vippsQuote));
    }

    public function testASecondRequestCannotUseTheSameToken(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('update')->willReturn(0);

        $this->assertFalse($this->createReturnToken($connection)->consume($this->vippsQuote(self::TOKEN)));
    }

    public function testNothingToConsumeWithoutAToken(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('update');

        $this->assertFalse($this->createReturnToken($connection)->consume($this->vippsQuote(null)));
    }

    private function createReturnToken(AdapterInterface $connection): ReturnToken
    {
        $resource = $this->createMock(QuoteResource::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('vipps_quote');

        return new ReturnToken($resource);
    }

    private function vippsQuote(?string $token): QuoteInterface
    {
        $vippsQuote = $this->createMock(QuoteInterface::class);
        $vippsQuote->method('getEntityId')->willReturn(7);
        $vippsQuote->method('getReturnToken')->willReturn($token);

        return $vippsQuote;
    }
}
