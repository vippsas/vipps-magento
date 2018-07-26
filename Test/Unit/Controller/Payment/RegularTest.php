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
    Controller\ResultFactory, Controller\ResultInterface, Exception\LocalizedException,
    TestFramework\Unit\Helper\ObjectManager, App\Action\Context
};
use Vipps\Payment\Api\CommandManagerInterface;
use Vipps\Payment\Controller\Payment\Regular;
use Magento\Checkout\Model\Session;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteRepository;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Vipps\Payment\Gateway\Exception\VippsException;

/**
 * Class RegularTest
 * @package Vipps\Payment\Test\Unit\Controller\Payment
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RegularTest extends TestCase
{
    /**
     * @var Regular
     */
    private $action;

    /**
     * @var CommandManagerInterface|MockObject
     */
    private $commandManager;

    /**
     * @var Session|MockObject
     */
    private $session;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var ResultInterface|MockObject
     */
    private $response;

    /**
     * @var ResultFactory|MockObject
     */
    private $resultFactory;

    /**
     * @var Quote|MockObject
     */
    private $quote;

    /**
     * @var QuoteRepository|MockObject
     */
    private $quoteRepository;

    /**
     * @var Payment|MockObject
     */
    private $quotePayment;

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->setMethods(['reserveOrderId', 'getPayment', 'getGrandTotal'])
            ->getMock();
        $this->quoteRepository = $this->getMockBuilder(QuoteRepository::class)
            ->disableOriginalConstructor()
            ->setMethods(['save'])
            ->getMock();
        $this->quotePayment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->quote->expects(self::any())
            ->method('reserveOrderId')
            ->willReturn($this->quote);
        $this->quote->expects(self::any())
            ->method('getPayment')
            ->willReturn($this->quotePayment);
        $this->session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->setMethods(['getQuote', 'clearStorage'])
            ->getMock();
        $this->session->expects(self::any())
            ->method('getQuote')
            ->willReturn($this->quote);
        $this->commandManager = $this->getMockBuilder(CommandManagerInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['getOrderStatus'])
            ->getMockForAbstractClass();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['critical'])
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
        $this->response = $this->getMockBuilder(ResultInterface::class)
            ->setMethods(['setData'])
            ->getMockForAbstractClass();
        $this->resultFactory->expects(self::once())
            ->method('create')
            ->with(ResultFactory::TYPE_JSON)
            ->willReturn($this->response);
        $managerHelper = new ObjectManager($this);
        $this->action = $managerHelper->getObject(Regular::class, [
            'context' => $context,
            'commandManager' => $this->commandManager,
            'session' => $this->session,
            'quoteRepository' => $this->quoteRepository,
            'logger' => $this->logger
        ]);
    }

    public function testExecuteLocalizedException()
    {
        $errorMessage = __('Can\'t initiate payment');
        $responseData = ['errorMessage' => $errorMessage];
        $exception = new LocalizedException($errorMessage);
        $this->commandManager->method('initiatePayment')
            ->willThrowException($exception);
        $this->response->expects(self::once())
            ->method('setData')
            ->with($responseData)
            ->willReturnSelf();
        self::assertEquals($this->action->execute(), $this->response);
    }

    public function testExecuteException()
    {
        $responseData = ['errorMessage' => __('An error occurred during request to Vipps. Please try again later.')];
        $exception = new \Exception();
        $this->commandManager->method('initiatePayment')
            ->willThrowException($exception);
        $this->response->expects(self::once())
            ->method('setData')
            ->with($responseData)
            ->willReturnSelf();
        self::assertEquals($this->action->execute(), $this->response);
    }

    public function testExecuteVippsException()
    {
        $errorMessage = __('Couldn\'t process this request. Please try again later or contact a store administrator.');
        $responseData = ['errorMessage' => $errorMessage];
        $exception = new VippsException(
            $errorMessage,
            null,
            35
        );
        $this->commandManager->method('initiatePayment')
            ->willThrowException($exception);
        $this->response->expects(self::once())
            ->method('setData')
            ->with($responseData)
            ->willReturnSelf();
        self::assertEquals($this->action->execute(), $this->response);
    }

    public function testExecute()
    {
        $responseData = [
            'orderId' => '12345',
            'url' => 'https://apitest.vipps.no'
        ];
        $this->quote->method('getGrandTotal')
            ->willReturn(1200);
        $this->commandManager->method('initiatePayment')
            ->willReturn($responseData);
        $this->response->expects(self::once())
            ->method('setData')
            ->with($responseData)
            ->willReturnSelf();
        self::assertEquals($this->action->execute(), $this->response);
    }
}
