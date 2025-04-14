<?php

declare(strict_types=1);

namespace Vipps\Payment\Model\Transaction;

use Vipps\Payment\Gateway\Transaction\Transaction;
use Vipps\Payment\GatewayEpayment\Data\Payment;

class StatusVisitor
{
    public function isExpired($transaction): bool
    {
        if ($transaction instanceof Transaction) {
            return $transaction->isTransactionExpired();
        }

        if ($transaction instanceof Payment) {
            return $transaction->isExpired();
        }

        return false;
    }

    public function isCanceled($transaction): bool
    {
        if ($transaction instanceof Transaction) {
            return $transaction->transactionWasCancelled();
        }

        if ($transaction instanceof Payment) {
            return $transaction->isTerminated();
        }

        return false;
    }

    public function isVoided($transaction): bool
    {
        if ($transaction instanceof Transaction) {
            return $transaction->transactionWasVoided();
        }

        if ($transaction instanceof Payment) {
            return $transaction->isAborter();
        }

        return false;
    }

    public function isAuthorised($transaction): bool
    {
        if ($transaction instanceof Payment) {
            return $transaction->isAuthorised();
        }

        return false;
    }

    public function isReserved($transaction): bool
    {
        if ($transaction instanceof Transaction) {
            return $transaction->isTransactionReserved();
        }

        if ($transaction instanceof Payment) {
            return $transaction->isAuthorised();
        }

        return false;
    }

    public function isCaptured($transaction): bool
    {
        if ($transaction instanceof Transaction) {
            return $transaction->isTransactionCaptured();
        }

        return false;
    }
}
