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
use Vipps\Payment\Model\CallbackAuthorization;

/**
 * The stored token is NULL or '' for every payment initiated before tokens were persisted, and for every
 * regular payment. Those must never authorise a callback, whatever the caller sends.
 *
 * @package Vipps\Payment\Test\Unit\Model
 */
class CallbackAuthorizationTest extends TestCase
{
    /**
     * @dataProvider tokenProvider
     */
    public function testIsAuthorized(?string $stored, ?string $presented, bool $expected): void
    {
        $this->assertSame($expected, (new CallbackAuthorization())->isAuthorized($stored, $presented));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: bool}>
     */
    public static function tokenProvider(): array
    {
        $token = 'a3f1c2d4e5b6978877665544332211ff';

        return [
            'matching token' => [$token, $token, true],
            'wrong token' => [$token, 'b3f1c2d4e5b6978877665544332211ff', false],
            'token prefix only' => [$token, substr($token, 0, 16), false],
            'header missing' => [$token, null, false],
            'header empty' => [$token, '', false],
            'stored token NULL' => [null, $token, false],
            'stored token empty' => ['', $token, false],
            // The case a loose comparison would let through.
            'both NULL' => [null, null, false],
            'both empty' => ['', '', false],
            'stored NULL, header empty' => [null, '', false],
        ];
    }
}
