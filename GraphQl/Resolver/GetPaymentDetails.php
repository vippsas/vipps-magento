<?php
/**
 * Copyright 2022 Vipps
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
namespace Vipps\Payment\GraphQl\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Vipps\Payment\Gateway\Command\PaymentDetailsProvider;

class GetPaymentDetails implements ResolverInterface
{
    /**
     * @var PaymentDetailsProvider 
     */
    private $detailsProvider;

    /**
     * GetPaymentDetails constructor.
     *
     * @param PaymentDetailsProvider $detailsProvider
     */
    public function __construct(
        PaymentDetailsProvider $detailsProvider
    ) {
        $this->detailsProvider = $detailsProvider;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $result = [];
        $incrementId = $args['order_number'] ?? null;

        if ($incrementId) {
            $transaction = $this->detailsProvider->get($incrementId);
            if ($transaction) {
                $result = [
                    'order_number' => $transaction->getOrderId(),
                    'cancelled' => $transaction->transactionWasCancelled() || $transaction->transactionWasVoided(),
                    'reserved' => $transaction->transactionWasReserved()
                ];
            }
        }

        return $result;
    }
}
