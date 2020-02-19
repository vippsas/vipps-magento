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

namespace Vipps\Payment\Gateway\Transaction;

/**
 * Class Transaction
 * @package Vipps\Payment\Gateway\Transaction
 */
class Transaction
{
    /**
     * @var string
     */
    const TRANSACTION_STATUS_INITIATE = 'initiate';

    /**
     * @var string
     */
    const TRANSACTION_STATUS_INITIATED = 'initiated';

    /**
     * @var string
     */
    const TRANSACTION_STATUS_REGISTER = 'register';

    /**
     * @var string
     */
    const TRANSACTION_STATUS_RESERVE = 'reserve';

    /**
     * @var string
     */
    const TRANSACTION_STATUS_RESERVED = 'reserved';

    /**
     * @var string
     */
    const TRANSACTION_STATUS_SALE = 'sale';

    /**
     * @var string
     */
    const TRANSACTION_STATUS_CANCEL = 'cancel';

    /**
     * @var string
     */
    const TRANSACTION_STATUS_CANCELLED = 'cancelled';

    /**
     * @var string
     */
    const TRANSACTION_STATUS_VOID = 'void';

    /**
     * @var string
     */
    const TRANSACTION_STATUS_AUTOREVERSAL = 'autoreversal';

    /**
     * @var string
     */
    const TRANSACTION_STATUS_AUTOCANCEL = 'autocancel';

    /**
     * @var string
     */
    const TRANSACTION_STATUS_FAILED = 'failed';

    /**
     * @var string
     */
    const TRANSACTION_STATUS_REJECTED = 'rejected';

    /**
     * @var string
     */
    const TRANSACTION_STATUS_CAPTURED = 'captured';

    /**
     * @var string
     */
    const TRANSACTION_STATUS_REFUND = 'refund';

    /**
     * @var string
     */
    const TRANSACTION_OPERATION_RESERVE = 'reserve';

    /**
     * @var string
     */
    const TRANSACTION_OPERATION_CAPTURE = 'capture';

    /**
     * @var string
     */
    const TRANSACTION_OPERATION_REFUND = 'refund';

    /**
     * @var string
     */
    const TRANSACTION_OPERATION_CANCEL = 'cancel';

    /**
     * @var string
     */
    const TRANSACTION_OPERATION_VOID = 'void';

    /**
     * @var string
     */
    private $orderId;

    /**
     * @var TransactionInfo
     */
    private $transactionInfo;

    /**
     * @var TransactionSummary
     */
    private $transactionSummary;

    /**
     * @var TransactionLogHistory
     */
    private $transactionLogHistory;

    /**
     * @var UserDetails
     */
    private $userDetails;

    /**
     * @var ShippingDetails
     */
    private $shippingDetails;

    /**
     * Transaction constructor.
     *
     * @param string $orderId
     * @param TransactionInfo $transactionInfo
     * @param TransactionSummary $transactionSummary
     * @param TransactionLogHistory $transactionLogHistory
     * @param UserDetails|null $userDetails
     * @param ShippingDetails|null $shippingDetails
     */
    public function __construct(
        $orderId,
        TransactionInfo $transactionInfo,
        TransactionSummary $transactionSummary,
        TransactionLogHistory $transactionLogHistory,
        UserDetails $userDetails = null,
        ShippingDetails $shippingDetails = null
    ) {
        $this->orderId = $orderId;
        $this->transactionInfo = $transactionInfo;
        $this->transactionSummary = $transactionSummary;
        $this->transactionLogHistory = $transactionLogHistory;
        $this->userDetails = $userDetails;
        $this->shippingDetails = $shippingDetails;
    }

    /**
     * @return string
     */
    public function getOrderId()
    {
        return $this->orderId;
    }

    /**
     * @return TransactionSummary
     */
    public function getTransactionSummary()
    {
        return $this->transactionSummary;
    }

    /**
     * @return null|UserDetails
     */
    public function getUserDetails()
    {
        return $this->userDetails;
    }

    /**
     * @return null|ShippingDetails
     */
    public function getShippingDetails()
    {
        return $this->shippingDetails;
    }

    /**
     * @return bool
     */
    public function isExpressCheckout()
    {
        return $this->userDetails === null ? false : true;
    }

    /**
     * Is initiate transaction.
     *
     * @return bool
     */
    public function isInitiate()
    {
        return $this->getTransactionInfo()->getStatus() === Transaction::TRANSACTION_STATUS_INITIATE;
    }

    /**
     * @return TransactionInfo
     */
    public function getTransactionInfo()
    {
        return $this->transactionInfo;
    }

    /**
     * @return bool
     */
    public function isTransactionAborted()
    {
        $abortedStatuses = [
            Transaction::TRANSACTION_STATUS_CANCEL,
            Transaction::TRANSACTION_STATUS_CANCELLED,
            Transaction::TRANSACTION_STATUS_AUTOCANCEL,
            Transaction::TRANSACTION_STATUS_REJECTED,
            Transaction::TRANSACTION_STATUS_FAILED,
            Transaction::TRANSACTION_STATUS_VOID
        ];

        return in_array($this->getTransactionInfo()->getStatus(), $abortedStatuses);
    }

    /**
     * Check that transaction has been cancelled
     *
     * @return bool
     */
    public function isTransactionCancelled()
    {
        foreach ($this->getTransactionLogHistory()->getItems() as $item) {
            if ($item->getOperation() != Transaction::TRANSACTION_OPERATION_CANCEL) {
                continue;
            }

            return $item->isOperationSuccess();
        }
        return false;
    }

    /**
     * Check that transaction has been reserved
     *
     * @return bool
     */
    public function isTransactionReserved()
    {
        foreach ($this->getTransactionLogHistory()->getItems() as $item) {
            if ($item->getOperation() != Transaction::TRANSACTION_OPERATION_RESERVE) {
                continue;
            }

            return $item->isOperationSuccess();
        }
        return false;
    }

    /**
     * Method to retrieve Transaction Id.
     *
     * @return null|string
     */
    public function getTransactionId()
    {
        return $this->getTransactionInfo()->getTransactionId() ?:
            $this->getTransactionLogHistory()->getLastTransactionId();
    }

    /**
     * @return TransactionLogHistory
     */
    public function getTransactionLogHistory()
    {
        return $this->transactionLogHistory;
    }
}
