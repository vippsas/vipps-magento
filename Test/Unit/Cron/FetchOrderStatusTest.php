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
namespace Vipps\Payment\Test\Unit\Cron;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Vipps\Payment\Api\CommandManagerInterface;
use Vipps\Payment\Cron\FetchOrderStatus;
use PHPUnit\Framework\TestCase;

/**
 * Class FetchOrderStatusTest
 * @package Vipps\Payment\Test\Unit\Cron
 */
class FetchOrderStatusTest extends TestCase
{
    /**
     * @var FetchOrderStatus
     */
    private $action;

    /**
     * @var CommandManagerInterface|MockObject
     */
    private $commandManager;

    /**
     * @var ObjectManager
     */
    private $objectManagerHelper;

    /**
     * @var Order|MockObject
     */
    private $orderMock;

    /**
     * @var CollectionFactory|MockObject
     */
    private $orderCollectionFactoryMock;

    protected function setUp()
    {
        $this->orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderCollectionFactoryMock = $this->getMockBuilder(CollectionFactory::class)
                ->disableOriginalConstructor()
                ->setMethods(['create'])
                ->getMock();

        $this->commandManager = $this->getMockBuilder(CommandManagerInterface::class)
                ->disableOriginalConstructor()
                ->setMethods(['getOrderStatus'])
                ->getMockForAbstractClass();

        $this->objectManagerHelper = new ObjectManager($this);

        $this->action = $this->objectManagerHelper->getObject(FetchOrderStatus::class, [
            'orderCollectionFactory' => $this->orderCollectionFactoryMock,
            'commandManager' => $this->commandManager
        ]);
    }

    /**
     * @param $count
     * @dataProvider dataProvider
     */
    public function testExecute($count)
    {
        $orderCollectionItems = [];
        for ($i = 0; $i < $count; $i++) {
            $orderCollectionItems[] = $this->orderMock;
        }

        $orderCollectionMock = $this->objectManagerHelper
            ->getCollectionMock(Collection::class, $orderCollectionItems);

        $this->orderCollectionFactoryMock
            ->expects($this->once())
            ->method('create')
            ->willReturn($orderCollectionMock);

        $orderCollectionMock->expects($this->atLeast(2))
            ->method('addFieldToFilter')
            ->willReturnSelf();

        $this->commandManager->expects($this->exactly($count))
            ->method('getOrderStatus');

        $this->action->execute();
    }

    public function dataProvider()
    {
        return [
            [0],
            [1],
        ];
    }
}
