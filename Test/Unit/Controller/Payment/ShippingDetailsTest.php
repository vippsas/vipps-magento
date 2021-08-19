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
     * @var ShippingMethodInterface|MockObject
     */
    private $shippingMethod;

    protected function setUp(): void //@codingStandardsIgnoreLine
    {
        $this->cartRepository = $this->getMockBuilder(CartRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['getList', 'getItems'])
            ->getMockForAbstractClass();

        $this->quoteLocator = $this->getMockBuilder(QuoteLocator::class)
            ->disableOriginalConstructor()
            ->setMethods(['get'])
            ->getMock();

        $this->quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->setMethods(['getId', 'setIsActive'])
            ->getMock();

        $this->serializer = $this->getMockBuilder(Json::class)
            ->disableOriginalConstructor()
            ->setMethods(['unserialize'])
            ->getMock();

        $this->shipmentEstimation = $this->getMockBuilder(ShipmentEstimationInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['estimateByExtendedAddress'])
            ->getMockForAbstractClass();

        $this->addressFactory = $this->getMockBuilder(AddressInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();

        $this->address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->setMethods(['addData'])
            ->getMockForAbstractClass();

        $this->request = $this->getMockBuilder(RequestInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['getParams', 'getContent'])
            ->getMockForAbstractClass();

        $this->resultFactory = $this->getMockBuilder(ResultFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();

        $this->response = $this->getMockBuilder(ResultInterface::class)
            ->setMethods(['setData', 'setHttpResponseCode'])
            ->getMockForAbstractClass();
        $this->resultFactory
            ->method('create')
            ->with(ResultFactory::TYPE_JSON)
            ->willReturn($this->response);

        $this->shippingMethod = $this->getMockBuilder(ShippingMethodInterface::class)
            ->setMethods(['getAmount', 'getMethodCode', 'getCarrierCode'])
            ->getMockForAbstractClass();

        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->setMethods(['critical', 'debug'])
            ->getMockForAbstractClass();

        $this->addressFactory
            ->method('create')
            ->willReturn($this->address);

        $this->addressUpdater = $this->getMockBuilder(AddressUpdater::class)
            ->disableOriginalConstructor()
            ->setMethods(['fromSourceAddress'])
            ->getMock();

        $this->compliance = $this->getMockBuilder(Compliance::class)
            ->disableOriginalConstructor()
            ->setMethods(['process'])
            ->getMock();

        $this->shippingMethodValidator = $this->getMockBuilder(ShippingMethodValidator::class)
            ->disableOriginalConstructor()
            ->setMethods(['isValid'])
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
            'logger' => $this->logger
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

    public function testExecuteLocalizedException()
    {
        $reservedOrderId = '1000024';
        $params = [
            '1000023' => [],
            $reservedOrderId => [],
        ];
        $this->request->method('getParams')
            ->willReturn($params);

        $errorStatus = Response::STATUS_CODE_500;
        $responseData = [
            'status' => $errorStatus,
            'message' => \__('An error occurred during Shipping Details processing.')
        ];
        $this->response->expects(self::once())
            ->method('setHttpResponseCode')
            ->with($errorStatus)
            ->willReturnSelf();

        $this->response->expects(self::once())
            ->method('setData')
            ->with($responseData)
            ->willReturnSelf();

        $content = 'some bad content';
        $this->request->expects(self::exactly(2))
            ->method('getContent')
            ->willReturn($content);

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

    public function testExecute()
    {
        $reservedOrderId = (int)'1000024';
        $params = [
            '1000023' => [],
            $reservedOrderId => [],
        ];
        $this->request->method('getParams')
            ->willReturn($params);

        $this->quoteLocator->expects(self::once())
            ->method('get')
            ->with($reservedOrderId)
            ->willReturn($this->quote);

        $unSerializedValue =
            '{a:6:{s:8:"postCode";s:4:"1234";s:12:"addressLine1";s:4:"city";s:4:"Oslo";s:10:"country_id";s:2:"NO";}';
        $this->request->expects(self::exactly(2))
            ->method('getContent')
            ->willReturn($unSerializedValue);

        $serializedValue  = [
            'addressId' => '123333',
            'postCode' => '1232',
            'addressLine1' => 'street name',
            'addressLine2' => 'house number',
            'address_type' => 'shipping',
            'city' => 'city'
        ];
        $this->serializer->expects(self::once())
            ->method('unserialize')
            ->with($unSerializedValue)
            ->willReturn($serializedValue);

        $this->addressFactory
            ->method('create')
            ->willReturn($this->address);

        $quoteId = 1111;
        $this->quote->expects(self::exactly(1))
            ->method('getId')
            ->willReturn($quoteId);

        $this->addressUpdater->expects(self::once())
            ->method('fromSourceAddress')
            ->with($this->quote, $this->address)
            ->willReturnSelf();

        $this->quote->expects(self::once())
            ->method('setIsActive')
            ->with(true)
            ->willReturnSelf();

        $this->shipmentEstimation->expects(self::once())
            ->method('estimateByExtendedAddress')
            ->with($quoteId, $this->address)
            ->willReturn([$this->shippingMethod, $this->shippingMethod]);

        $this->shippingMethod->expects(self::exactly(2))
            ->method('getCarrierCode')
            ->willReturn('carrier_code');

        $this->shippingMethod->expects(self::exactly(2))
            ->method('getMethodCode')
            ->willReturn('method_code');

        $this->shippingMethodValidator->expects(self::exactly(2))
            ->method('isValid')
            ->with('carrier_code_method_code')
            ->willReturn(true);

        $this->shippingMethod->expects(self::exactly(2))
            ->method('getAmount')
            ->willReturn(10);

        $this->shippingMethod->expects(self::exactly(2))
            ->method('getMethodTitle')
            ->willReturn('method_title');

        $responseData = [
            'addressId' => '123333',
            'orderId' => $reservedOrderId,
            'shippingDetails' => [
                [
                    'isDefault' => 'N',
                    'priority' => 0,
                    'shippingCost' => 10,
                    'shippingMethod' => 'method_title',
                    'shippingMethodId' => 'carrier_code_method_code',
                ],
                [
                    'isDefault' => 'N',
                    'priority' => 1,
                    'shippingCost' => 10,
                    'shippingMethod' => 'method_title',
                    'shippingMethodId' => 'carrier_code_method_code',
                ]
            ]
        ];
        $this->response->expects(self::once())
            ->method('setHttpResponseCode')
            ->with(Response::STATUS_CODE_200)
            ->willReturnSelf();

        $this->response->expects(self::once())
            ->method('setData')
            ->with($responseData)
            ->willReturnSelf();

        $compliantString = 'compliantString';
        $this->compliance->expects(self::once())
            ->method('process')
            ->with($unSerializedValue)
            ->willReturn($compliantString);

        $this->logger->expects(self::once())
            ->method('debug')
            ->with($compliantString)
            ->willReturnSelf();

        self::assertEquals($this->action->execute(), $this->response);
    }
}
