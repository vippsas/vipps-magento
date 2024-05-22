<?php

declare(strict_types=1);

namespace Vipps\Payment\Model\QuoteUpdater;

use Magento\Quote\Model\Quote;
use Vipps\Payment\Gateway\Transaction\Transaction;
use Vipps\Payment\Model\Method\Vipps;
use Vipps\Payment\Model\QuoteUpdater\BillingAddressUpdater\FromSub;
use Vipps\Payment\Model\QuoteUpdater\BillingAddressUpdater\FromTransaction;

class BillingAddressUpdater
{
    private FromSub $fromSub;
    private FromTransaction $fromTransaction;

    public function __construct(FromSub $fromSub, FromTransaction $fromTransaction)
    {
        $this->fromSub = $fromSub;
        $this->fromTransaction = $fromTransaction;
    }

    private function isExpress(Quote $quote)
    {
        return $quote
                ->getPayment()
                ->getAdditionalInformation('method_type') === Vipps::METHOD_TYPE_EXPRESS_CHECKOUT;
    }

    public function update(Quote $quote, Transaction $transaction)
    {
        if ($this->isExpress($quote) && $quote->isVirtual()) {
            $this->fromSub->update($quote, $transaction);
        } else {
            $this->fromTransaction->update($quote, $transaction);
        }
    }
}
