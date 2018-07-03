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
     * @param TransactionInfo $transactionInfo
     * @param TransactionSummary $transactionSummary
     * @param TransactionLogHistory $transactionLogHistory
     * @param UserDetails|null $userDetails
     * @param ShippingDetails|null $shippingDetails
     */
    public function __construct(
        TransactionInfo $transactionInfo,
        TransactionSummary $transactionSummary,
        TransactionLogHistory $transactionLogHistory,
        UserDetails $userDetails = null,
        ShippingDetails $shippingDetails = null
    ) {
        $this->transactionInfo = $transactionInfo;
        $this->transactionSummary = $transactionSummary;
        $this->transactionLogHistory = $transactionLogHistory;
        $this->userDetails = $userDetails;
        $this->shippingDetails = $shippingDetails;
    }

    /**
     * @return TransactionInfo
     */
    public function getTransactionInfo()
    {
        return $this->transactionInfo;
    }

    /**
     * @return TransactionSummary
     */
    public function getTransactionSummary()
    {
        return $this->transactionSummary;
    }

    /**
     * @return TransactionLogHistory
     */
    public function getTransactionLogHistory()
    {
        return $this->transactionLogHistory;
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
        return $this->userDetails === null  ? false : true;
    }

    /**
     * Method to retrieve Transaction Status.
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->getTransactionInfo()->getStatus() ?:
            $this->getTransactionLogHistory()->getLastTransactionStatus();
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
}
