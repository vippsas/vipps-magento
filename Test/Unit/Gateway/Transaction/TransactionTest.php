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

namespace Vipps\Payment\Test\Unit\Gateway\Transaction;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use Vipps\Payment\Gateway\Transaction\ShippingDetails;
use Vipps\Payment\Gateway\Transaction\Transaction;
use Vipps\Payment\Gateway\Transaction\TransactionInfo;
use Vipps\Payment\Gateway\Transaction\TransactionLogHistory;
use Vipps\Payment\Gateway\Transaction\TransactionLogHistory\Item;
use Vipps\Payment\Gateway\Transaction\TransactionSummary;
use Vipps\Payment\Gateway\Transaction\UserDetails;

/**
 * Class TransactionTest
 * @package Vipps\Payment\Test\Unit\Gateway\Transaction
 */
class TransactionTest extends TestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManagerHelper;

    protected function setUp()
    {
        $this->objectManagerHelper = new ObjectManager($this);
    }

    public function testIsTransactionInitiated()
    {
        $data = [
            ['expected' => true, 'actual' => include __DIR__ . '/_files/initiated_transaction_case01.php'],
            ['expected' => false, 'actual' => include __DIR__ . '/_files/initiated_transaction_case02.php'],
            ['expected' => false, 'actual' => include __DIR__ . '/_files/initiated_transaction_case03.php'],
            ['expected' => true, 'actual' => include __DIR__ . '/_files/initiated_transaction_case04.php'],
        ];

        foreach ($data as $datum) {
            $transaction = $this->buildTransaction($datum['actual']);
            $this->assertEquals($datum['expected'], $transaction->isTransactionInitiated());
        }
    }

    public function testIsTransactionReserved()
    {
        $data = [
            ['expected' => true, 'actual' => include __DIR__ . '/_files/reserved_transaction_case01.php'],
            ['expected' => false, 'actual' => include __DIR__ . '/_files/reserved_transaction_case02.php'],
            ['expected' => false, 'actual' => include __DIR__ . '/_files/reserved_transaction_case03.php'],
        ];

        foreach ($data as $datum) {
            $transaction = $this->buildTransaction($datum['actual']);
            $this->assertEquals($datum['expected'], $transaction->isTransactionReserved());
        }
    }

    /**
     * @throws \Exception
     */
    public function testIsTransactionExpired()
    {
        $data = [
            ['expected' => true, 'actual' => include __DIR__ . '/_files/expired_transaction_case01.php'],
            ['expected' => false, 'actual' => include __DIR__ . '/_files/expired_transaction_case02.php'],
        ];

        foreach ($data as $datum) {
            $transaction = $this->buildTransaction($datum['actual']);
            $this->assertEquals($datum['expected'], $transaction->isTransactionExpired());
        }
    }

    /**
     * @param array $data
     *
     * @return object|Transaction
     */
    public function buildTransaction($data)
    {
        /** @var Transaction $transaction */
        $transactionInfo = $this->objectManagerHelper->getObject(
            TransactionInfo::class,
            [
                'data' => $data['transactionInfo'] ?? []
            ]
        );

        $transactionSummary = $this->objectManagerHelper->getObject(
            TransactionSummary::class,
            [
                'data' => $data['transactionSummary'] ?? []
            ]
        );

        $logHistoryData = $data['transactionLogHistory'] ?? [];
        $items = [];
        foreach ($logHistoryData as $itemData) {
            $items[] = $this->objectManagerHelper->getObject(Item::class, ['data' => $itemData]);
        }
        $transactionLogHistory = $this->objectManagerHelper
            ->getObject(TransactionLogHistory::class, ['data' => ['items' => $items]]);

        $userDetails = $this->objectManagerHelper->getObject(
            UserDetails::class,
            [
                'data' => $data['userDetails'] ?? []
            ]
        );

        $shippingDetails = $this->objectManagerHelper->getObject(
            ShippingDetails::class,
            [
                'data' => $data['shippingDetails'] ?? []
            ]
        );

        $transaction = $this->objectManagerHelper->getObject(
            Transaction::class,
            [
                'orderId' => $data['orderId'],
                'transactionInfo' => $transactionInfo,
                'transactionSummary' => $transactionSummary,
                'transactionLogHistory' => $transactionLogHistory,
                'userDetails' => $userDetails,
                'shippingDetails' => $shippingDetails
            ]
        );

        return $transaction;
    }
}
