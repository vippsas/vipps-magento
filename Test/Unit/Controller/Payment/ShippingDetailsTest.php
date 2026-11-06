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

namespace Vipps\Payment\Test\Unit\Controller\Payment;

use Laminas\Http\Response;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Controller\Payment\ShippingDetails;
use Vipps\Payment\Model\Express\ShippingCallbackGuard;
use Vipps\Payment\Model\Gdpr\Compliance;
use Vipps\Payment\Model\QuoteLocator;
use Vipps\Payment\Model\Quote\AddressUpdater;
use Vipps\Payment\Model\Quote\ShippingMethodValidator;

/**
 * Class ShippingDetailsTest
 * @package Vipps\Payment\Test\Unit\Controller\Payment
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 */
class ShippingDetailsTest extends TestCase
{
    /**
     * @var ShippingDetails
     */
    private $action;

    /**
     * @var CartRepositoryInterface|MockObject
     */
    private $cartRepository;

    /**
     * @var Json|MockObject
     */
    private $serializer;

    /**
     * @var ShipmentEstimationInterface|MockObject
     */
    private $shipmentEstimation;

    /**
     * @var AddressInterfaceFactory|MockObject
     */
    private $addressFactory;

    /**
     * @var Address|AddressInterface|MockObject
     */
    private $address;

    /**
     * @var ResultFactory|MockObject
     */
    private $resultFactory;

    /**
     * @var ResultInterface|MockObject
     */
    private $response;

    /**
     * @var RequestInterface|MockObject
     */
    protected $request;  //@codingStandardsIgnoreLine

    /**
     * @var QuoteLocator|MockObject
     */
    private $quoteLocator;

    /**
     * @var Quote|MockObject
     */
    private $quote;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var AddressUpdater|MockObject
     */
    private $addressUpdater;

    /**
     * @var Compliance|MockObject
     */
    private $compliance;

    /**
     * @var ShippingMethodValidator|MockObject
     */
    private $shippingMethodValidator;

    /**
     * @var ShippingCallbackGuard|MockObject
     */
    private $callbackGuard;

    /**
     * @var ShippingMethodInterface|MockObject
     */
    private $shippingMethod;

    protected function setUp(): void //@codingStandardsIgnoreLine
    {
        $this->cartRepository = $this->getMockBuilder(CartRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getList'])
            ->addMethods(['getItems'])
            ->getMockForAbstractClass();

        $this->quoteLocator = $this->getMockBuilder(QuoteLocator::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();

        $this->quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'setIsActive'])
            ->addMethods(['getStoreCurrencyCode'])
            ->getMock();

        $this->serializer = $this->getMockBuilder(Json::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['unserialize'])
            ->getMock();

        $this->shipmentEstimation = $this->getMockBuilder(ShipmentEstimationInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['estimateByExtendedAddress'])
            ->getMockForAbstractClass();

        $this->addressFactory = $this->getMockBuilder(AddressInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();

        $this->address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addData'])
            ->getMockForAbstractClass();

        $this->request = $this->getMockBuilder(RequestInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getParams'])
            ->addMethods(['getContent'])
            ->getMockForAbstractClass();

        $this->resultFactory = $this->getMockBuilder(ResultFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();

        $this->response = $this->getMockBuilder(ResultInterface::class)
            ->onlyMethods(['setHttpResponseCode'])
            ->addMethods(['setData'])
            ->getMockForAbstractClass();
        $this->resultFactory
            ->method('create')
            ->with(ResultFactory::TYPE_JSON)
            ->willReturn($this->response);

        $this->shippingMethod = $this->getMockBuilder(ShippingMethodInterface::class)
            ->onlyMethods(['getAmount', 'getMethodCode', 'getCarrierCode', 'getMethodTitle', 'getPriceInclTax'])
            ->getMockForAbstractClass();

        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->onlyMethods(['critical', 'debug'])
            ->getMockForAbstractClass();

        $this->addressFactory
            ->method('create')
            ->willReturn($this->address);

        $this->addressUpdater = $this->getMockBuilder(AddressUpdater::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['fromSourceAddress'])
            ->getMock();

        $this->compliance = $this->getMockBuilder(Compliance::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['process'])
            ->getMock();

        // The guard is exercised by ShippingDetailsAuthorizationTest; here every request is authentic.
        $this->callbackGuard = $this->getMockBuilder(ShippingCallbackGuard::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRejectionStatus'])
            ->getMock();
        $this->callbackGuard->method('getRejectionStatus')->willReturn(null);

        $this->shippingMethodValidator = $this->getMockBuilder(ShippingMethodValidator::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isValid'])
            ->getMock();

        $managerHelper = new ObjectManager($this);
        $this->action = $managerHelper->getObject(ShippingDetails::class, [
            'resultFactory' => $this->resultFactory,
            'request' => $this->request,
            'cartRepository' => $this->cartRepository,
            'quoteLocator' => $this->quoteLocator,
            'serializer' => $this->serializer,
            'shipmentEstimation' => $this->shipmentEstimation,
            'addressFactory' => $this->addressFactory,
            'addressUpdater' => $this->addressUpdater,
            'shippingMethodValidator' => $this->shippingMethodValidator,
            'compliance' => $this->compliance,
            'logger' => $this->logger,
            'callbackGuard' => $this->callbackGuard
        ]);
    }

    public function testExecuteException()
    {
        $reservedOrderId = '1000024';
        $params = [
            '1000023' => [],
            $reservedOrderId => [],
        ];
        $this->request->method('getParams')
            ->willReturn($params);

        $this->quoteLocator->expects(self::never())
            ->method('get')
            ->with($reservedOrderId)
            ->willReturn($this->quote);

        $content = 'some bad content';
        $this->request->expects(self::exactly(2))
            ->method('getContent')
            ->willReturn($content);

        $errorMessage1 = "Unable to unserialize value. Error: xxx";
        $exception = new \InvalidArgumentException($errorMessage1);
        $this->serializer->expects(self::once())
            ->method('unserialize')
            ->with($content)
            ->willThrowException($exception);

        $errorStatus = Response::STATUS_CODE_500;
        $errorMessage2 = __('An error occurred during Shipping Details processing.');
        $responseData = [
            'status' => $errorStatus,
            'message' => $errorMessage2
        ];

        $this->response->expects(self::once())
            ->method('setHttpResponseCode')
            ->with($errorStatus)
            ->willReturnSelf();

        $this->response->expects(self::once())
            ->method('setData')
            ->with($responseData)
            ->willReturnSelf();

        $compliantString = 'compliantString';
        $this->compliance->expects(self::once())
            ->method('process')
            ->with($content)
            ->willReturn($compliantString);

        $this->logger->expects(self::once())
            ->method('debug')
            ->with($compliantString)
            ->willReturnSelf();

        self::assertEquals($this->action->execute(), $this->response);
    }

    /**
     * A callback naming a reference the store has no cart for. It must answer 500 with the reason,
     * not fall through to the generic handler, and it must still log compliantly.
     */
    public function testExecuteLocalizedException()
    {
        $payload = json_encode([
            'reference' => '1000024',
            'postCode' => '0154',
            'addressLine1' => 'Karl Johans gate',
            'addressLine2' => '1',
            'city' => 'Oslo',
        ]);

        $this->request->method('getContent')->willReturn($payload);
        $this->serializer->method('unserialize')->with($payload)->willReturn(json_decode($payload, true));
        $this->addressFactory->method('create')->willReturn($this->address);

        // No cart carries that reference, so getQuote() raises LocalizedException.
        $this->quoteLocator->expects(self::once())->method('get')->with('1000024')->willReturn(null);
        $this->cartRepository->expects(self::never())->method('save');

        $this->response->expects(self::once())
            ->method('setHttpResponseCode')
            ->with(Response::STATUS_CODE_500);
        $this->response->expects(self::once())
            ->method('setData')
            ->with([
                'status' => Response::STATUS_CODE_500,
                // getMessage() unwraps the Phrase, so the response carries a plain string.
                'message' => 'Requested quote not found',
            ]);

        $this->compliance->method('process')->with($payload)->willReturn('compliant');
        $this->logger->expects(self::once())->method('debug')->with('compliant');

        self::assertSame($this->response, $this->action->execute());
    }

    /**
     * The happy path: a shipping callback Vipps/MobilePay authenticated is answered with the store's
     * own rates, in the ePayment `groups` shape, priced as the order will actually charge.
     */
    public function testExecute()
    {
        $reservedOrderId = '1000024';
        $quoteId = 1111;

        $payload = json_encode([
            'reference' => $reservedOrderId,
            'postCode' => '0154',
            'addressLine1' => 'Karl Johans gate',
            'addressLine2' => '1',
            'city' => 'Oslo',
        ]);

        $this->request->method('getContent')->willReturn($payload);
        $this->serializer->method('unserialize')->with($payload)->willReturn(json_decode($payload, true));

        $this->addressFactory->method('create')->willReturn($this->address);
        $this->address->expects(self::once())->method('addData');

        $this->quoteLocator->expects(self::once())
            ->method('get')
            ->with($reservedOrderId)
            ->willReturn($this->quote);
        $this->quote->method('getId')->willReturn($quoteId);
        $this->quote->method('getStoreCurrencyCode')->willReturn('NOK');

        // The cart is deactivated while the payment is live, so it is reactivated to estimate rates.
        $this->quote->expects(self::once())->method('setIsActive')->with(true);
        $this->addressUpdater->expects(self::once())->method('fromSourceAddress')->with($this->quote, $this->address);
        $this->cartRepository->expects(self::once())->method('save')->with($this->quote);

        $this->shippingMethod->method('getCarrierCode')->willReturn('flatrate');
        $this->shippingMethod->method('getMethodCode')->willReturn('flatrate');
        $this->shippingMethod->method('getMethodTitle')->willReturn('Fixed');
        $this->shippingMethod->method('getAmount')->willReturn(149.00);
        $this->shippingMethod->method('getPriceInclTax')->willReturn(186.25);

        $this->shipmentEstimation->expects(self::once())
            ->method('estimateByExtendedAddress')
            ->with($quoteId, $this->address)
            ->willReturn([$this->shippingMethod]);

        $this->shippingMethodValidator->method('isValid')->with('flatrate_flatrate')->willReturn(true);

        $this->response->expects(self::once())->method('setHttpResponseCode')->with(200);
        $this->response->expects(self::once())
            ->method('setData')
            ->with([
                'groups' => [
                    [
                        'isDefault' => true,
                        'priority' => 0,
                        'type' => 'OTHER',
                        'brand' => 'OTHER',
                        'options' => [
                            [
                                'id' => 'flatrate_flatrate',
                                // Minor units, tax inclusive: what the authorisation is topped up by.
                                'amount' => ['value' => 18625, 'currency' => 'NOK'],
                                'name' => 'Fixed',
                            ],
                        ],
                    ],
                ],
            ]);

        $this->compliance->method('process')->with($payload)->willReturn('compliant');
        $this->logger->expects(self::once())->method('debug')->with('compliant');

        self::assertSame($this->response, $this->action->execute());
    }
}
