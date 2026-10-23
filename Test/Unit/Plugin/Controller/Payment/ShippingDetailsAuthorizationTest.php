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

namespace Vipps\Payment\Test\Unit\Plugin\Controller\Payment;

use Laminas\Http\Response;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\ResultFactory;
use PHPUnit\Framework\TestCase;
use Vipps\Payment\Controller\Payment\ShippingDetails;
use Vipps\Payment\Model\Express\ShippingCallbackGuard;
use Vipps\Payment\Plugin\Controller\Payment\ShippingDetailsAuthorization;

/**
 * Integrations replace ShippingDetails::execute() with around plugins that never call proceed, so the check
 * inside the controller is not enough on its own. This plugin wraps them all and must stop the chain on
 * rejection.
 *
 * @package Vipps\Payment\Test\Unit\Plugin\Controller\Payment
 */
class ShippingDetailsAuthorizationTest extends TestCase
{
    public function testAuthorisedRequestProceeds(): void
    {
        $downstream = $this->createMock(JsonResult::class);
        $called = false;

        $returned = $this->plugin(null)->aroundExecute(
            $this->createMock(ShippingDetails::class),
            function () use (&$called, $downstream) {
                $called = true;
                return $downstream;
            }
        );

        $this->assertTrue($called);
        $this->assertSame($downstream, $returned);
    }

    /**
     * @dataProvider rejectionProvider
     */
    public function testRejectedRequestNeverReachesTheChain(int $status): void
    {
        $result = $this->createMock(JsonResult::class);
        $result->expects($this->once())->method('setHttpResponseCode')->with($status)->willReturnSelf();
        $result->method('setData')->willReturnSelf();

        $returned = $this->plugin($status, $result)->aroundExecute(
            $this->createMock(ShippingDetails::class),
            function () {
                // Stands in for the controller and any integration's plugin that would mutate the cart.
                $this->fail('An unauthenticated request reached the rest of the chain');
            }
        );

        $this->assertSame($result, $returned);
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function rejectionProvider(): array
    {
        return [
            'unauthenticated' => [Response::STATUS_CODE_403],
            'malformed' => [Response::STATUS_CODE_400],
        ];
    }

    private function plugin(?int $rejectionStatus, ?JsonResult $result = null): ShippingDetailsAuthorization
    {
        $request = $this->createMock(HttpRequest::class);

        $guard = $this->createMock(ShippingCallbackGuard::class);
        $guard->method('getRejectionStatus')->with($request)->willReturn($rejectionStatus);

        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->method('create')->willReturn($result ?? $this->createMock(JsonResult::class));

        return new ShippingDetailsAuthorization($guard, $request, $resultFactory);
    }
}
