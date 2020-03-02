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

namespace Vipps\Payment\Test\Unit\Gateway\Response;

use Magento\Framework\{Exception\LocalizedException, TestFramework\Unit\Helper\ObjectManager};
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction as PaymentTransaction;
use Vipps\Payment\Gateway\Request\SubjectReader;
use Vipps\Payment\Gateway\Response\TransactionHandler;
use Vipps\Payment\Gateway\Transaction\{
    Transaction, TransactionBuilder, TransactionInfo, TransactionLogHistory, TransactionSummary, UserDetails,
    ShippingDetails
};
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class TransactionHandler
 * @package Vipps\Payment\Gateway\Response
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class TransactionHandlerTest extends TestCase
{
    /**
     * @var TransactionHandler
     */
    private $action;

    /**
     * @var ObjectManager
     */
    private $objectManagerHelper;

    /**
     * @var Payment|MockObject
     */
    private $payment;

    /**
     * @var PaymentDataObjectInterface|MockObject
     */
    private $paymentDataObject;

    /**
     * @var SubjectReader|MockObject
     */
    private $subjectReader;

    /**
     * @var TransactionBuilder
     */
    private $transactionBuilder;

    protected function setUp()
    {
        $this->objectManagerHelper = new ObjectManager($this);

        $this->subjectReader = $this->getMockBuilder(SubjectReader::class)
            ->disableOriginalConstructor()
            ->setMethods(['readPayment'])
            ->getMock();

        $this->transactionBuilder = $this->objectManagerHelper->getObject(TransactionBuilder::class, [
            'transactionFactory' => $this->getMockFactory(Transaction::class),
            'infoFactory' => $this->getMockFactory(TransactionInfo::class),
            'summaryFactory' => $this->getMockFactory(TransactionSummary::class),
            'logHistoryFactory' => $this->getMockFactory(TransactionLogHistory::class),
            'itemFactory' => $this->getMockFactory(TransactionLogHistory\Item::class),
            'userDetailsFactory' => $this->getMockFactory(UserDetails::class),
            'shippingDetailsFactory' => $this->getMockFactory(ShippingDetails::class),
        ]);

        $this->payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->paymentDataObject = $this->getMockBuilder(PaymentDataObjectInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['getPayment'])
            ->getMockForAbstractClass();

        $this->action = $this->objectManagerHelper->getObject(TransactionHandler::class, [
            'subjectReader' => $this->subjectReader,
            'transactionBuilder' => $this->transactionBuilder
        ]);
    }

    /**
     * @param $payment
     * @param $count
     * @dataProvider dataProvider
     */
    public function testHandle($payment, $count)
    {
        $this->subjectReader->expects(self::once())
            ->method('readPayment')
            ->with([])
            ->willReturn($this->paymentDataObject);

        if ($payment) {
            $payment = $this->payment;
        }

        $this->paymentDataObject->expects(self::once())
            ->method('getPayment')
            ->willReturn($payment);

        $this->payment->expects($this->exactly($count))
            ->method('setTransactionId')
            ->willReturnSelf();

        $this->action->handle([], ['orderId' => 'testOrderId']);
    }

    public function dataProvider()
    {
        return [
            [0, 0],
            [1, 1],
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
            ->will($this->returnCallback(function ($args) use ($instanceName, $objectManager) {
                return $objectManager->getObject($instanceName, $args);
            }));
        return $factory;
    }
}
