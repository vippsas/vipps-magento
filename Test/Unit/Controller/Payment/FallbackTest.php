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

use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Api\QuoteRepositoryInterface;
use Vipps\Payment\Controller\Payment\Fallback;
use Vipps\Payment\Gateway\Command\PaymentDetailsProvider;
use Vipps\Payment\Gateway\Transaction\ShippingDetails;
use Vipps\Payment\Gateway\Transaction\Transaction;
use Vipps\Payment\Gateway\Transaction\TransactionBuilder;
use Vipps\Payment\Gateway\Transaction\TransactionInfo;
use Vipps\Payment\Gateway\Transaction\TransactionLogHistory;
use Vipps\Payment\Gateway\Transaction\TransactionSummary;
use Vipps\Payment\Gateway\Transaction\UserDetails;
use Vipps\Payment\Model\Gdpr\Compliance;
use Vipps\Payment\Model\LockManager;
use Vipps\Payment\Model\OrderLocator;
use Vipps\Payment\Model\QuoteLocator;
use Vipps\Payment\Model\QuoteManagement;
use Vipps\Payment\Model\QuoteUpdater;
use Vipps\Payment\Model\TransactionProcessor;

/**
 * Class FallbackTestTest
 * @package Vipps\Payment\Test\Unit\Controller\Payment
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class FallbackTest extends TestCase
{
    /**
     * @var Fallback
     */
    private $action;
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
     * @var PaymentDetailsProvider|MockObject
     */
    private $paymentDetailsProvider;
    /**
     * @var ManagerInterface|MockObject
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
    /**
     * @var MockObject
     */
    private $transactionProcessor;
    /**
     * @var MockObject
     */
    private $vippsQuoteRepository;
    /**
     * @var MockObject
     */
    private $compliance;

    /**
     * @var MockObject
     */
    private $vippsQuote;
    /**
     * @var TransactionBuilder
     */
    private $transactionBuilder;
    /**
     * @var MockObject
     */
    private $orderRepository;
    /**
     * @var MockObject
     */
    private $cartManagement;
    /**
     * @var MockObject
     */
    private $quoteLocator;
    /**
     * @var MockObject
     */
    private $processor;
    /**
     * @var MockObject
     */
    private $quoteUpdater;
    /**
     * @var MockObject
     */
    private $lockManager;
    /**
     * @var MockObject
     */
    private $config;
    /**
     * @var MockObject
     */
    private $configMock;
    /**
     * @var MockObject
     */
    private $quoteManagement;
    /**
     * @var
     */
    private $orderLocator;

    protected function setUp(): void
    {
        $this->action = $this->createActionInstance();
    }

    /**
     * @param $orderId
     * @param $token
     *
     * @throws CouldNotSaveException
     *
     * @dataProvider invalidParamsDataProvider
     */
    public function testExecuteWithInvalidParams($orderId, $token)
    {
        $this->request
            ->expects($this->any())
            ->method('getParam')
            ->with($this->logicalOr(
                $this->equalTo('order_id'),
                $this->equalTo('auth_token')
            ))
            ->will($this->returnCallback(function ($param) use ($orderId, $token) {
                if ($param == 'order_id') {
                    return $orderId;
                } else {
                    return $token;
                }
            }));

        $this->vippsQuote
            ->method('getAuthToken')
            ->willReturn($token . ' make it invalid');

        $this->configMock
            ->expects($this->once())
            ->method('getValue')
            ->willReturn(true);

        $this->messageManagerMock->expects(self::once())
            ->method('addErrorMessage');
        $this->resultRedirect->expects(self::once())
            ->method('setPath')->with('checkout/cart');

        $this->action->execute();
    }

    /**
     * @return array
     */
    public function invalidParamsDataProvider()
    {
        return [
            ['00000000001', null],
            [null, 'some token'],
            ['00000000001', 'some token'],
        ];
    }

    public function testCouldNotAcquireLock()
    {
        $this->authorize();

        $transaction = $this->buildTransaction(include __DIR__ . '/_files/could_not_acquire_lock.php');
        $this->paymentDetailsProvider->method('get')
            ->willReturn($transaction);

        $this->vippsQuote->method('getReservedOrderId')->willReturn(null);

        $this->messageManagerMock->expects(self::once())
            ->method('addErrorMessage');
        $this->resultRedirect->expects(self::once())
            ->method('setPath')->with('checkout/onepage/failure');

        $this->action->execute();
    }

    public function testTransactionWasCancelled()
    {
        $this->authorize();

        $transaction = $this->buildTransaction(include __DIR__ . '/_files/cancelled_transaction.php');
        $this->paymentDetailsProvider->method('get')
            ->willReturn($transaction);

        $this->vippsQuote->method('getReservedOrderId')->willReturn('000000001');
        $this->vippsQuote->method('getOrderId')->willReturn('000000001');

        $this->quoteManagement
            ->expects($this->once())
            ->method('save');

        $this->order->expects($this->once())->method('getState')->willReturn('new');

        $this->lockManager->method('lock')->willReturn(true);

        $this->vippsQuote
            ->expects($this->once())
            ->method('setStatus')
            ->with(QuoteInterface::STATUS_CANCELED);

        $this->configMock
            ->expects($this->any())
            ->method('getValue')
            ->willReturn(true);

        $this->expectRestoreQuote();

        $this->messageManagerMock->expects(self::once())
            ->method('addWarningMessage');
        $this->resultRedirect->expects(self::once())
            ->method('setPath')->with('checkout/cart');

        $this->action->execute();
    }

    private function expectRestoreQuote()
    {
        $this->quote->expects($this->once())->method('setIsActive')->with(true);
        $this->quote->expects($this->once())->method('setReservedOrderId')->with(null);
        $this->cartRepository->expects($this->once())->method('save');

        $this->checkoutSession->expects($this->once())->method('replaceQuote');
    }

    public function testTransactionWasReservedWhenOrderExists()
    {
        $this->authorize();

        $transaction = $this->buildTransaction(include __DIR__ . '/_files/reserved_transaction.php');
        $this->paymentDetailsProvider->method('get')
            ->willReturn($transaction);

        $this->lockManager->method('lock')->willReturn(true);

        $this->vippsQuote->method('getReservedOrderId')->willReturn('000000001');
        $this->vippsQuote->method('getOrderId')->willReturn('000000001');

        // we set status to 'processing' to prevent capture/authorize execution in this test
        // otherwise test will be too complicated
        $this->order->expects($this->once())->method('getState')->willReturn('processing');

        $this->config
            ->expects($this->once())
            ->method('getValue')
            ->with('vipps_payment_action')
            ->willReturn('authorize');

        $this->vippsQuote
            ->expects($this->once())
            ->method('setStatus')
            ->with(QuoteInterface::STATUS_RESERVED);

        $this->quoteManagement
            ->expects($this->once())
            ->method('save');

        $this->resultRedirect->expects(self::once())
            ->method('setPath')->with('checkout/onepage/success');

        $this->action->execute();
    }

    private function authorize()
    {
        $this->request
            ->expects($this->any())
            ->method('getParam')
            ->with($this->logicalOr(
                $this->equalTo('order_id'),
                $this->equalTo('auth_token')
            ))
            ->will($this->returnCallback(function ($param) {
                if ($param == 'order_id') {
                    return '00000001';
                } else {
                    return 'valid token';
                }
            }));

        $this->vippsQuote
            ->method('getAuthToken')
            ->willReturn('valid token');
    }

    private function buildTransaction($data)
    {
        $this->transactionBuilder = $this->objectManagerHelper->getObject(TransactionBuilder::class, [
            'transactionFactory' => $this->getMockFactory(Transaction::class),
            'infoFactory' => $this->getMockFactory(TransactionInfo::class),
            'summaryFactory' => $this->getMockFactory(TransactionSummary::class),
            'logHistoryFactory' => $this->getMockFactory(TransactionLogHistory::class),
            'itemFactory' => $this->getMockFactory(TransactionLogHistory\Item::class),
            'userDetailsFactory' => $this->getMockFactory(UserDetails::class),
            'shippingDetailsFactory' => $this->getMockFactory(ShippingDetails::class),

        ]);

        return $this->transactionBuilder->setData($data)->build();
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
            ->will($this->returnCallback(function ($args) use ($instanceName, $objectManager) {
                return $objectManager->getObject($instanceName, $args);
            }));
        return $factory;
    }

    /**
     * @return object|Fallback
     */
    private function createActionInstance()
    {
        $this->objectManagerHelper = new ObjectManager($this);

        $this->resultRedirect = $this->getMockBuilder(Redirect::class)
            ->setMethods(['setPath'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->resultFactory = $this->getMockBuilder(ResultFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();
        $this->resultFactory->expects(self::once())
            ->method('create')
            ->with(ResultFactory::TYPE_REDIRECT)
            ->willReturn($this->resultRedirect);
        $this->request = $this->getMockBuilder(RequestInterface::class)
            ->setMethods(['getResultFactory', 'getRequest', 'getResponse', 'getParam', 'getRequestString'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->messageManagerMock = $this->getMockBuilder(ManagerInterface::class)
            ->setMethods(['addErrorMessage', 'addWarningMessage'])
            ->getMockForAbstractClass();

        $this->paymentDetailsProvider = $this->createPaymentDetailsProvider();
        $this->checkoutSession = $this->createCheckoutSession();
        $this->cartRepository = $this->createCartRepository();
        $this->vippsQuoteRepository = $this->createVippsQuoteRepository();

        $this->compliance = $this->getMockBuilder(Compliance::class)
            ->setMethods(['process'])->disableOriginalConstructor()->getMock();

        $this->logger = $this->getMockBuilder(LoggerInterface::class)->getMockForAbstractClass();

        $this->transactionProcessor = $this->createTransactionProcessor();

        $this->configMock = $this->getMockBuilder(ConfigInterface::class)
            ->setMethods(['getValue'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $this->action = $this->objectManagerHelper->getObject(Fallback::class, [
            'resultFactory' => $this->resultFactory,
            'request' => $this->request,
            'checkoutSession' => $this->checkoutSession,
            'transactionProcessor' => $this->transactionProcessor,
            'cartRepository' => $this->cartRepository,
            'messageManager' => $this->messageManagerMock,
            'vippsQuoteRepository' => $this->vippsQuoteRepository,
            'orderLocator' => $this->orderLocator,
            'compliance' => $this->compliance,
            'orderManagement' => $this->createOrderManagement(),
            'logger' => $this->logger,
            'config' => $this->configMock
        ]);

        return $this->action;
    }

    /**
     * @return MockObject
     */
    private function createPaymentDetailsProvider()
    {
        $paymentDetailsProvider = $this->getMockBuilder(PaymentDetailsProvider::class)
            ->disableOriginalConstructor()
            ->setMethods(['get'])
            ->getMock();

        return $paymentDetailsProvider;
    }

    /**
     * @return MockObject
     */
    private function createCheckoutSession()
    {
        $checkoutSession = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'setLastQuoteId',
                'setLastSuccessQuoteId',
                'setLastOrderId',
                'setLastRealOrderId',
                'setLastOrderStatus',
                'replaceQuote',
                'clearStorage',
                'getQuoteId'
            ])
            ->getMock();

        return $checkoutSession;
    }

    /**
     * @return MockObject
     */
    private function createOrderManagement()
    {
        $orderManagement = $this->getMockBuilder(OrderManagementInterface::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getOrderByIncrementId',
                'place',
                'getQuoteByReservedOrderId',
                'cancel',
                'notify'
            ])
            ->getMockForAbstractClass();

        return $orderManagement;
    }

    /**
     * @return MockObject
     */
    private function createVippsQuoteRepository()
    {
        $this->vippsQuote = $this->getMockBuilder(QuoteInterface::class)
            ->setMethods(['getAuthToken', 'getQuoteId', 'getOrderId', 'getReservedOrderId'])
            ->getMockForAbstractClass();

        $vippsQuoteRepository = $this->getMockBuilder(QuoteRepositoryInterface::class)
            ->setMethods(['loadByOrderId'])->disableOriginalConstructor()->getMockForAbstractClass();

        $vippsQuoteRepository
            ->method('loadByOrderId')
            ->willReturn($this->vippsQuote);

        return $vippsQuoteRepository;
    }

    /**
     * @return TransactionProcessor
     */
    private function createTransactionProcessor()
    {
        $this->order = $this->getMockBuilder(OrderInterface::class)
            ->setMethods(['getState', 'getStatus', 'getCanSendNewEmailFlag', 'getEmailSent', 'getEntityId'])
            ->getMockForAbstractClass();

        $this->orderRepository = $this->getMockBuilder(OrderRepositoryInterface::class)
            ->setMethods(['save', 'get'])->disableOriginalConstructor()->getMockForAbstractClass();
        $this->orderRepository->method('get')->willReturn($this->order);

        $this->cartManagement = $this->getMockBuilder(CartManagementInterface::class)
            ->setMethods(['save'])->disableOriginalConstructor()->getMockForAbstractClass();

        $this->quoteLocator = $this->getMockBuilder(QuoteLocator::class)
            ->setMethods(['save'])->disableOriginalConstructor()->getMock();

        $this->orderLocator = $this->getMockBuilder(OrderLocator::class)
            ->setMethods(['get'])->disableOriginalConstructor()->getMock();
        $this->orderLocator->method('get')->willReturn($this->order);

        $this->processor = $this->getMockBuilder(Order\Payment\Processor::class)
            ->setMethods(['save'])->disableOriginalConstructor()->getMock();

        $this->quoteUpdater = $this->getMockBuilder(QuoteUpdater::class)
            ->setMethods(['save'])->disableOriginalConstructor()->getMock();

        $this->lockManager = $this->getMockBuilder(LockManager::class)
            ->setMethods(['lock', 'unlock'])->disableOriginalConstructor()->getMock();

        $this->config = $this->getMockBuilder(ConfigInterface::class)
            ->setMethods(['save', 'getValue'])->disableOriginalConstructor()->getMockForAbstractClass();

        $this->quoteManagement = $this->getMockBuilder(QuoteManagement::class)
            ->setMethods(['save', 'reload'])->disableOriginalConstructor()->getMockForAbstractClass();
        $this->quoteManagement->method('reload')->willReturn($this->vippsQuote);

        $transactionProcessor = $this->objectManagerHelper->getObject(
            TransactionProcessor::class,
            [
                'orderRepository' => $this->orderRepository,
                'cartRepository' => $this->cartRepository,
                'cartManagement' => $this->cartManagement,
                'quoteLocator' => $this->quoteLocator,
                'processor' => $this->processor,
                'quoteUpdater' => $this->quoteUpdater,
                'lockManager' => $this->lockManager,
                'config' => $this->config,
                'quoteManagement' => $this->quoteManagement,
                'orderManagement' => $this->createOrderManagement(),
                'orderLocator' => $this->orderLocator,
                'logger' => $this->logger,
                'paymentDetailsProvider' => $this->paymentDetailsProvider
            ]
        );

        return $transactionProcessor;
    }

    private function createCartRepository()
    {
        $this->quote = $this->getMockBuilder(CartInterface::class)
            ->setMethods(['getId', 'setIsActive', 'setReservedOrderId'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $cartRepository = $this->getMockBuilder(CartRepositoryInterface::class)
            ->setMethods(['save', 'get'])->disableOriginalConstructor()->getMockForAbstractClass();

        $cartRepository->method('get')
            ->willReturn($this->quote);

        return $cartRepository;
    }
}
