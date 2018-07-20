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

use Magento\Framework\{
    App\RequestInterface, Controller\ResultFactory, Controller\ResultInterface, Message\ManagerInterface,
    TestFramework\Unit\Helper\ObjectManager, App\Action\Context
};
use Magento\Checkout\Model\Session;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Vipps\Payment\{
    Controller\Payment\Fallback, Gateway\Request\Initiate\MerchantDataBuilder, Gateway\Transaction\ShippingDetails,
    Gateway\Command\PaymentDetailsProvider, Gateway\Transaction\Transaction, Gateway\Transaction\TransactionBuilder,
    Gateway\Transaction\TransactionInfo, Gateway\Transaction\TransactionLogHistory,
    Gateway\Transaction\TransactionSummary, Gateway\Transaction\UserDetails, Model\OrderPlace,
    Api\CommandManagerInterface
};

/**
 * Class FallbackTestTest
 * @package Vipps\Payment\Test\Unit\Controller\Payment
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class FallbackTestTest extends TestCase
{
    /**
     * @var Fallback
     */
    private $action;

    /**
     * @var CommandManagerInterface|MockObject
     */
    private $commandManager;

    /**
     * @var OrderPlace|MockObject
     */
    private $orderManagement;

    /**
     * @var Session|MockObject
     */
    private $checkoutSession;

    /**
     * @var ResultFactory|MockObject
     */
    private $resultFactory;

    /**
     * @var ResultInterface|MockObject
     */
    private $resultRedirect;

    /**
     * @var Quote|MockObject
     */
    private $quote;

    /**
     * @var Order|MockObject
     */
    private $order;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var RequestInterface|MockObject
     */
    private $request;

    /**
     * @var TransactionBuilder|MockObject
     */
    private $transactionBuilder;

    /**
     * @var PaymentDetailsProvider|MockObject
     */
    private $paymentDetailsProvider;

    /**
     * @var ManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $messageManagerMock;

    /**
     * @var ObjectManager
     */
    private $objectManagerHelper;

    /**
     * @var CartRepositoryInterface|MockObject
     */
    private $cartRepository;

    protected function setUp()
    {
        $this->markTestSkipped('Skipped since deprecated, will be coveren in new patch release');

        $this->resultRedirect = $this->getMockBuilder(ResultInterface::class)
            ->setMethods(['setPath'])
            ->getMockForAbstractClass();
        $this->resultFactory = $this->getMockBuilder(ResultFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();
        $this->resultFactory->expects(self::once())
            ->method('create')
            ->with(ResultFactory::TYPE_REDIRECT)
            ->willReturn($this->resultRedirect);
        $this->orderManagement = $this->getMockBuilder(OrderPlace::class)
            ->disableOriginalConstructor()
            ->setMethods(['getOrderByIncrementId', 'place', 'getQuoteByReservedOrderId'])
            ->getMock();
        $this->paymentDetailsProvider = $this->getMockBuilder(PaymentDetailsProvider::class)
            ->disableOriginalConstructor()
            ->setMethods(['get'])
            ->getMock();

        $this->checkoutSession = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'setLastQuoteId', 'setLastSuccessQuoteId', 'setLastOrderId', 'setLastRealOrderId', 'setLastOrderStatus'
            ])
            ->getMock();
        $this->commandManager = $this->getMockBuilder(CommandManagerInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['getOrderStatus'])
            ->getMockForAbstractClass();

        $this->messageManagerMock = $this->getMockBuilder(ManagerInterface::class)
            ->setMethods(['addErrorMessage'])
            ->getMockForAbstractClass();
        $context = $this->getMockBuilder(Context::class)
            ->setMethods(['getMessageManager', 'getRequest', 'getResponse', 'getResultFactory'])
            ->disableOriginalConstructor()
            ->getMock();
        $context->expects(self::once())
            ->method('getResultFactory')
            ->willReturn($this->resultFactory);
        $this->request = $this->getMockBuilder(RequestInterface::class)
            ->setMethods(['getResultFactory', 'getRequest', 'getResponse', 'getParam'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $context->expects(self::once())
            ->method('getRequest')
            ->willReturn($this->request);
        $context->expects(self::once())
            ->method('getMessageManager')
            ->willReturn($this->messageManagerMock);
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->getMockForAbstractClass();

        $this->cartRepository = $this->getMockBuilder(CartRepositoryInterface::class)
            ->setMethods(['save', ])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $this->quote = $this->getMockBuilder(CartInterface::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'reserveOrderId', 'getReservedOrderId', 'getId', 'getPayment', 'setAdditionalInformation',
                'getAdditionalInformation'
            ])
            ->getMockForAbstractClass();
        $this->objectManagerHelper = new ObjectManager($this);
        $this->transactionBuilder = $this->objectManagerHelper->getObject(TransactionBuilder::class, [
            'transactionFactory' => $this->getMockFactory(Transaction::class),
            'infoFactory' => $this->getMockFactory(TransactionInfo::class),
            'summaryFactory' => $this->getMockFactory(TransactionSummary::class),
            'logHistoryFactory' => $this->getMockFactory(TransactionLogHistory::class),
            'itemFactory' => $this->getMockFactory(TransactionLogHistory\Item::class),
            'userDetailsFactory' => $this->getMockFactory(UserDetails::class),
            'shippingDetailsFactory' => $this->getMockFactory(ShippingDetails::class),

        ]);
        $this->action = $this->objectManagerHelper->getObject(Fallback::class, [
            'context' => $context,
            'commandManager' => $this->commandManager,
            'checkoutSession' => $this->checkoutSession,
            'transactionBuilder' => $this->transactionBuilder,
            'paymentDetailsProvider' => $this->paymentDetailsProvider,
            'orderManagement' => $this->orderManagement,
            'logger' => $this->logger
        ]);
    }

    /**
     * @param $order
     * @param $response
     * @param bool $isExpectedException
     * @param $additionalInfo
     * @param $accessToken
     *
     * @dataProvider dataProvider
     */
    public function testExecute($order, $response, $isExpectedException, $additionalInfo, $accessToken)
    {
        $this->request->method('getParam')
            ->willReturn($accessToken);
        $this->orderManagement->expects(self::any())
            ->method('getOrderByIncrementId')
            ->willReturn($order);
        $this->quote->expects(self::any())
            ->method('setAdditionalInformation')
            ->willReturnSelf();
        $this->quote->expects(self::any())
            ->method('getPayment')
            ->willReturnSelf();
        $this->quote->expects(self::any())
            ->method('getAdditionalInformation')
            ->willReturn($additionalInfo);
        $this->orderManagement->expects(self::once())
            ->method('getQuoteByReservedOrderId')
            ->willReturn($this->quote);
        if (!$order) {
            $this->paymentDetailsProvider->expects(self::once())
                ->method('get')
                ->willReturn($response);
            $this->orderManagement->expects(self::any())
                ->method('place')
                ->willReturnSelf();
        } else {
            $this->quote->method('getId')
                ->willReturn('id1');
            $order->method('getId')
                ->willReturn('id1');
            $order->method('getStatus')
                ->willReturn('Status');
            $order->method('getIncrementId')
                ->willReturn('id');
        }

        if ($isExpectedException) {
            $this->resultRedirect->expects(self::once())
                ->method('setPath')
                ->with('checkout/onepage/failure')
                ->willReturnSelf();
        } else {
            $this->resultRedirect->expects(self::once())
                ->method('setPath')
                ->with('checkout/onepage/success')
                ->willReturnSelf();
        }

        self::assertEquals($this->action->execute(), $this->resultRedirect);
    }

    /**
     * @dataProvider
     */
    public function dataProvider()
    {
        $response2 = [
            'orderId' => 'ffs000000062cx',
            'transactionSummary' => ['capturedAmount' => 0, 'remainingAmountToCapture' => 6400,'refundedAmount' => 0,
                                     'remainingAmountToRefund' => 0,],
            'transactionLogHistory' => [0 =>['amount' => 6400,'operation' => 'reserved','requestId' => '',
                                             'transactionText' => 'Thank you for shopping. Order Id: ffs000000062cx',
                                             'transactionId' => '5001424263','timeStamp' => '2018-06-14T11:03:38.130Z',
                                             'operationSuccess' => true,],
                                        1 =>['amount' => 5900,
                                             'transactionText' => 'Thank you for shopping. Order Id: ffs000000062cx',
                                             'transactionId' => '5001424263','timeStamp' => '2018-06-14T11:03:07.772Z',
                                             'operation' => 'INITIATE', 'requestId' => '']],
            'shippingDetails' => ['address' => ['addressLine1' => 'BOKS 6300, ETTERSTAD',
                                                'addressLine2' => 'BOKS 6300, ETTER',
                                                'postCode' => '0603', 'city' => 'oslo', 'country' => 'Norway',],
                                  'shippingMethod' => 'flatrate',
                                  'shippingCost' => '5.00',
                                  'shippingMethodId' => 'flatrate_flatrate',],
            'userDetails' => ['userId' => '10002778', 'firstName' => 'Laila', 'lastName' => 'Myller',
                              'mobileNumber' => '96750317', 'email' => 'integration@vipps.no',],
        ];
        $accessToken1 = 'token1';
        $additionalInfo1 = [MerchantDataBuilder::FALLBACK_AUTH_TOKEN => $accessToken1];
        $accessToken2 = 'token2';
        $additionalInfo2 = [MerchantDataBuilder::FALLBACK_AUTH_TOKEN => $accessToken1];
        $this->order = $this->getMockBuilder(OrderInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['getStatus', 'getIncrementId', 'getId'])
            ->getMockForAbstractClass();

        return [
            //order, response, isExceptionExpected, additionalInfo, accessToken
            [$this->order, $response2, false, $additionalInfo1, $accessToken1],
            [$this->order, $response2, true, $additionalInfo2, $accessToken2],
            [false, $response2, false, $additionalInfo1, $accessToken1],
            [false, null, true, $additionalInfo1, $accessToken1],
            [$this->order, null, false, $additionalInfo1, $accessToken1],
        ];
    }

    private function getMockFactory($instanceName)
    {
        $objectManager = $this->objectManagerHelper;
        $factory = $this->getMockBuilder($instanceName . 'Factory')
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();
        $factory->expects($this->any())
            ->method('create')
            ->will($this->returnCallback(function($args) use ($instanceName, $objectManager) {
                return $objectManager->getObject($instanceName, $args);
            }));
        return $factory;
    }
}


