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
use Vipps\Payment\GatewayEpayment\Command\PaymentDetailsProvider;
use Magento\Payment\Gateway\ConfigInterface;
use Vipps\Payment\GraphQl\ReturnAuthorizer;
use Vipps\Payment\Model\Transaction\StatusVisitor;

class GetPaymentDetails implements ResolverInterface
{
    /**
     * @var PaymentDetailsProvider
     */
    private $detailsProvider;
    /**
     * @var ConfigInterface
     */
    private $config;
    /**
     * @var ReturnAuthorizer
     */
    private $returnAuthorizer;
    /**
     * @var StatusVisitor
     */
    private $statusVisitor;

    /**
     * GetPaymentDetails constructor.
     *
     * @param PaymentDetailsProvider $detailsProvider
     * @param ConfigInterface $config
     * @param ReturnAuthorizer $returnAuthorizer
     * @param StatusVisitor $statusVisitor
     */
    public function __construct(
        PaymentDetailsProvider $detailsProvider,
        ConfigInterface $config,
        ReturnAuthorizer $returnAuthorizer,
        StatusVisitor $statusVisitor
    ) {
        $this->detailsProvider = $detailsProvider;
        $this->config = $config;
        $this->returnAuthorizer = $returnAuthorizer;
        $this->statusVisitor = $statusVisitor;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        $result = [];
        $incrementId = $args['order_number'] ?? null;

        $cartPersistence = $this->config->getValue('cancellation_cart_persistence');

        if ($incrementId) {
            // Not consumed here: the storefront reads the details first and then calls vippsActivateCart.
            $this->returnAuthorizer->authorize((string)$incrementId, (string)($args['token'] ?? ''), $context);

            $transaction = $this->detailsProvider->get($incrementId);
            if ($transaction) {
                // Read through StatusVisitor: the transactionWas*() methods belonged to the pre-ePayment model and
                // no longer exist, so this query failed for every payment since the ePayment migration.
                $result = [
                    'order_number' => $transaction->getReference(),
                    'cancelled' => $this->statusVisitor->isCanceled($transaction)
                        || $this->statusVisitor->isVoided($transaction),
                    'reserved' => $this->statusVisitor->isReserved($transaction),
                    'restore_cart' => $cartPersistence && $this->statusVisitor->isRestorable($transaction)
                ];
            }
        }

        return $result;
    }
}
