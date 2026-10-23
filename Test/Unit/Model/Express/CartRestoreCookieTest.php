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

namespace Vipps\Payment\Test\Unit\Model\Express;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadata;
use Magento\Framework\Stdlib\CookieManagerInterface;
use PHPUnit\Framework\TestCase;
use Vipps\Payment\Model\Express\CartRestoreCookie;

class CartRestoreCookieTest extends TestCase
{
    private ?string $cookie = null;

    public function testReadsBackTheQuoteItWasIssuedFor(): void
    {
        $cookie = $this->createCookie();

        $cookie->setPending(1234);

        $this->assertSame(1234, $cookie->getPending());
    }

    /**
     * @dataProvider forgedValueProvider
     */
    public function testRejectsAValueItDidNotIssue(string $value): void
    {
        $this->cookie = $value;

        $this->assertSame(0, $this->createCookie()->getPending());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function forgedValueProvider(): array
    {
        return [
            // The format before 3.0.5, which anyone could write for any quote.
            'a bare quote id' => ['1235'],
            'a guessed signature' => ['1235:' . str_repeat('0', 64)],
            'no signature' => ['1235:'],
            'not a quote id' => ['abc:' . str_repeat('0', 64)],
            'empty' => [''],
        ];
    }

    public function testASignatureCannotBeMovedToAnotherQuote(): void
    {
        $cookie = $this->createCookie();
        $cookie->setPending(1234);
        [, $signature] = explode(':', (string)$this->cookie, 2);

        $this->cookie = '1235:' . $signature;

        $this->assertSame(0, $cookie->getPending());
    }

    private function createCookie(): CartRestoreCookie
    {
        $cookieManager = $this->createMock(CookieManagerInterface::class);
        $cookieManager->method('getCookie')->willReturnCallback(fn () => $this->cookie);
        $cookieManager->method('setPublicCookie')->willReturnCallback(
            function ($name, $value) {
                $this->cookie = $value;
            }
        );

        $metadata = $this->createMock(PublicCookieMetadata::class);
        $metadata->method('setDuration')->willReturnSelf();
        $metadata->method('setPath')->willReturnSelf();
        $metadataFactory = $this->createMock(CookieMetadataFactory::class);
        $metadataFactory->method('createPublicCookieMetadata')->willReturn($metadata);

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('hash')->willReturnCallback(fn ($data) => hash_hmac('sha256', $data, 'test-key'));

        return new CartRestoreCookie($cookieManager, $metadataFactory, $encryptor);
    }
}
