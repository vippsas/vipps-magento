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

namespace Vipps\Payment\Test\Unit\Controller\Payment;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Controller\Payment\ShippingDetails;
use Vipps\Payment\Model\OrderManagement;
use Zend\Http\Response as ZendResponse;

/**
 * Class ShippingDetailsTest
 * @package Vipps\Payment\Test\Unit\Controller\Payment
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
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
     * @var AddressInterface|MockObject
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
     * @var OrderManagement|MockObject
     */
    private $orderManagement;

    /**
     * @var Quote|MockObject
     */
    private $quote;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var ShippingMethodInterface|MockObject
     */
    private $shippingMethod;

    protected function setUp() //@codingStandardsIgnoreLine
    {
        $this->cartRepository = $this->getMockBuilder(CartRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['getList', 'getItems'])
            ->getMockForAbstractClass();
        $this->orderManagement = $this->getMockBuilder(OrderManagement::class)
            ->disableOriginalConstructor()
            ->setMethods(['getQuoteByReservedOrderId'])
            ->getMock();
        $this->quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->setMethods(['getId'])
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
        $this->address = $this->getMockBuilder(AddressInterface::class)
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
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $context->expects(self::once())
            ->method('getResultFactory')
            ->willReturn($this->resultFactory);
        $context->expects(self::once())
            ->method('getRequest')
            ->willReturn($this->request);
        $this->response = $this->getMockBuilder(ResultInterface::class)
            ->setMethods(['setData'])
            ->getMockForAbstractClass();
        $this->resultFactory->expects(self::once())
            ->method('create')
            ->with(ResultFactory::TYPE_JSON)
            ->willReturn($this->response);
        $this->shippingMethod = $this->getMockBuilder(ShippingMethodInterface::class)
            ->setMethods(['getAmount', 'getMethodCode', 'getCarrierCode'])
            ->getMockForAbstractClass();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->getMockForAbstractClass();
        $managerHelper = new ObjectManager($this);
        $this->action = $managerHelper->getObject(ShippingDetails::class, [
            'context' => $context,
            'cartRepository' => $this->cartRepository,
            'orderManagement' => $this->orderManagement,
            'serializer' => $this->serializer,
            'shipmentEstimation' => $this->shipmentEstimation,
            'addressFactory' => $this->addressFactory,
            'logger' => $this->logger
        ]);
    }

    public function testExecuteException()
    {
        $errorStatus = ZendResponse::STATUS_CODE_500;
        $errorMessage = __('An error occurred during Shipping Details processing.');
        $responseData = [
            'status' => $errorStatus,
            'message' => $errorMessage
        ];
        $exception = new \Exception();
        $this->request->method('getParams')
            ->willThrowException($exception);
        $this->response->expects(self::once())
            ->method('setData')
            ->with($responseData)
            ->willReturnSelf();

        self::assertEquals($this->action->execute(), $this->response);
    }

    public function testExecuteLocalizedException()
    {
        $errorStatus = ZendResponse::STATUS_CODE_500;
        $errorMessage = __('Requested Quote does not exist');
        $responseData = [
            'status' => $errorStatus,
            'message' => $errorMessage
        ];
        $this->response->expects(self::once())
            ->method('setData')
            ->with($responseData)
            ->willReturnSelf();
        $params = [
            '1000023' => [],
            '1000024' => [],
        ];
        $this->request->method('getParams')
            ->willReturn($params);
        $this->orderManagement->expects(self::any())
            ->method('getQuoteByReservedOrderId')
            ->willThrowException(new LocalizedException(__('Requested Quote does not exist')));
        $this->quote->expects(self::any())
            ->method('getId')
            ->willReturn($this->quote);

        self::assertEquals($this->action->execute(), $this->response);
    }

    public function testExecute()
    {
        $params = [
            '1000023' => [],
            '1000024' => [],
        ];
        $this->request->method('getParams')
            ->willReturn($params);
        $unserializedValue = 'a:1:{s:9:"addressId";s:6:"123333";}';
        $this->request->expects(self::once())
            ->method('getContent')
            ->willReturn($unserializedValue);
        $this->serializer->expects(self::once())
            ->method('unserialize')
            ->willReturn(['addressId' => '123333']);
        $this->addressFactory->expects(self::once())
            ->method('create')
            ->willReturn($this->address);
        $this->address->expects(self::any())
            ->method('addData')
            ->willReturnSelf();
        $this->shipmentEstimation->expects(self::any())
            ->method('estimateByExtendedAddress')
            ->willReturn([$this->shippingMethod]);

        $this->response->expects(self::once())
            ->method('setData')
            ->willReturnSelf();

        self::assertEquals($this->action->execute(), $this->response);
    }
}
