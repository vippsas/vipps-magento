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

namespace Vipps\Payment\Test\Unit\Controller\Payment;

use Laminas\Http\Response;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Api\QuoteRepositoryInterface;
use Vipps\Payment\Controller\Payment\ShippingDetails;
use Vipps\Payment\Model\CallbackAuthorization;
use Vipps\Payment\Model\Express\ShippingCallbackGuard;
use Vipps\Payment\Model\Gdpr\Compliance;
use Vipps\Payment\Model\Quote\AddressUpdater;
use Vipps\Payment\Model\Quote\ShippingMethodValidator;
use Vipps\Payment\Model\QuoteLocator;

/**
 * The shipping details callback is public and keyed on a sequential reference, so the
 * callbackAuthorizationToken is the only thing standing between the internet and the shopper's cart.
 * Every rejection must happen before the cart is loaded, changed, reactivated or saved.
 *
 * @package Vipps\Payment\Test\Unit\Controller\Payment
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ShippingDetailsAuthorizationTest extends TestCase
{
    private const REFERENCE = '1200337124';
    private const TOKEN = 'a3f1c2d4e5b6978877665544332211ff';

    /** @var HttpRequest|MockObject */
    private $request;

    /** @var JsonResult|MockObject */
    private $result;

    /** @var QuoteRepositoryInterface|MockObject */
    private $vippsQuoteRepository;

    /** @var QuoteLocator|MockObject */
    private $quoteLocator;

    /** @var AddressUpdater|MockObject */
    private $addressUpdater;

    /** @var CartRepositoryInterface|MockObject */
    private $cartRepository;

    /** @var ShipmentEstimationInterface|MockObject */
    private $shipmentEstimation;

    /** @var array<string, mixed> */
    private $body = ['reference' => self::REFERENCE, 'postCode' => '0150', 'addressLine1' => 'Street 1',
        'addressLine2' => '', 'city' => 'Oslo'];

    /** @var mixed */
    private $header = self::TOKEN;

    /** @var string|null */
    private $storedToken = self::TOKEN;

    /** @var int|null */
    private $statusCode;

    protected function setUp(): void
    {
        $this->request = $this->createMock(HttpRequest::class);
        $this->request->method('getContent')->willReturn('{}');
        $this->request->method('getHeader')->willReturnCallback(
            fn ($name) => strcasecmp($name, 'Authorization') === 0 ? $this->header : false
        );

        $this->result = $this->createMock(JsonResult::class);
        $this->result->method('setHttpResponseCode')->willReturnCallback(function ($code) {
            $this->statusCode = $code;
            return $this->result;
        });
        $this->result->method('setData')->willReturnSelf();

        $this->vippsQuoteRepository = $this->createMock(QuoteRepositoryInterface::class);
        $this->quoteLocator = $this->createMock(QuoteLocator::class);
        $this->addressUpdater = $this->createMock(AddressUpdater::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->shipmentEstimation = $this->createMock(ShipmentEstimationInterface::class);
    }

    public function testValidTokenProceedsToShippingEstimation(): void
    {
        $cart = $this->createMock(Quote::class);
        $cart->method('getId')->willReturn(42);
        $cart->expects($this->once())->method('setIsActive')->with(true)->willReturnSelf();
        $this->quoteLocator->expects($this->once())->method('get')->with(self::REFERENCE)->willReturn($cart);
        $this->addressUpdater->expects($this->once())->method('fromSourceAddress');
        $this->cartRepository->expects($this->once())->method('save')->with($cart);
        $this->shipmentEstimation->expects($this->once())->method('estimateByExtendedAddress')->willReturn([]);

        $this->execute();

        $this->assertSame(Response::STATUS_CODE_200, $this->statusCode);
    }

    public function testMissingHeaderIsRejected(): void
    {
        $this->header = false;
        $this->assertRejectedWithoutMutation(Response::STATUS_CODE_403);
    }

    public function testEmptyHeaderIsRejected(): void
    {
        $this->header = '';
        $this->assertRejectedWithoutMutation(Response::STATUS_CODE_403);
    }

    public function testWrongTokenIsRejected(): void
    {
        $this->header = 'b3f1c2d4e5b6978877665544332211ff';
        $this->assertRejectedWithoutMutation(Response::STATUS_CODE_403);
    }

    public function testQuoteWithoutStoredTokenIsRejected(): void
    {
        // Every quote initiated before tokens were persisted is NULL; a missing header must not match it.
        $this->storedToken = null;
        $this->header = false;
        $this->assertRejectedWithoutMutation(Response::STATUS_CODE_403);
    }

    public function testQuoteWithEmptyStoredTokenIsRejected(): void
    {
        $this->storedToken = '';
        $this->header = '';
        $this->assertRejectedWithoutMutation(Response::STATUS_CODE_403);
    }

    public function testUnknownReferenceIsRejectedLikeAWrongToken(): void
    {
        $this->storedToken = null;
        $this->vippsQuoteRepository->method('loadByOrderId')
            ->willThrowException(new NoSuchEntityException(__('No such entity')));

        $this->assertRejectedWithoutMutation(Response::STATUS_CODE_403);
    }

    public function testMissingReferenceIsRejected(): void
    {
        unset($this->body['reference']);
        $this->vippsQuoteRepository->expects($this->never())->method('loadByOrderId');

        $this->assertRejectedWithoutMutation(Response::STATUS_CODE_400);
    }

    private function assertRejectedWithoutMutation(int $expectedStatus): void
    {
        $this->quoteLocator->expects($this->never())->method('get');
        $this->addressUpdater->expects($this->never())->method('fromSourceAddress');
        $this->cartRepository->expects($this->never())->method('save');
        $this->shipmentEstimation->expects($this->never())->method('estimateByExtendedAddress');

        $this->execute();

        $this->assertSame($expectedStatus, $this->statusCode);
    }

    private function execute(): void
    {
        $vippsQuote = $this->createMock(QuoteInterface::class);
        $vippsQuote->method('getAuthToken')->willReturn($this->storedToken);
        $this->vippsQuoteRepository->method('loadByOrderId')->willReturn($vippsQuote);

        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->method('create')->willReturn($this->result);

        $serializer = $this->createMock(Json::class);
        $serializer->method('unserialize')->willReturn($this->body);

        $compliance = $this->createMock(Compliance::class);
        $compliance->method('process')->willReturn('{}');

        $addressFactory = $this->createMock(AddressInterfaceFactory::class);
        $addressFactory->method('create')->willReturn($this->createMock(Address::class));

        $action = new ShippingDetails(
            $resultFactory,
            $this->request,
            $this->quoteLocator,
            $compliance,
            $serializer,
            $this->shipmentEstimation,
            $addressFactory,
            $this->addressUpdater,
            $this->createMock(ShippingMethodValidator::class),
            $this->cartRepository,
            $this->createMock(LoggerInterface::class),
            new ShippingCallbackGuard(
                $this->vippsQuoteRepository,
                new CallbackAuthorization(),
                $serializer,
                $this->createMock(LoggerInterface::class)
            )
        );

        $this->assertSame($this->result, $action->execute());
    }
}
