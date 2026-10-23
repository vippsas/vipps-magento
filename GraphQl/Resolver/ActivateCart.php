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
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResourceModel;
use Vipps\Payment\GatewayEpayment\Command\PaymentDetailsProvider;
use Vipps\Payment\GraphQl\ReturnAuthorizer;
use Vipps\Payment\Model\ReturnToken;
use Vipps\Payment\Model\Transaction\StatusVisitor;

class ActivateCart implements ResolverInterface
{
    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;
    /**
     * @var QuoteIdMaskResourceModel
     */
    private $quoteIdMaskResourceModel;
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;
    /**
     * @var ReturnAuthorizer
     */
    private $returnAuthorizer;
    /**
     * @var ReturnToken
     */
    private $returnToken;
    /**
     * @var PaymentDetailsProvider
     */
    private $detailsProvider;
    /**
     * @var ConfigInterface
     */
    private $config;
    /**
     * @var StatusVisitor
     */
    private $statusVisitor;

    /**
     * ActivateCart constructor.
     *
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param QuoteIdMaskResourceModel $quoteIdMaskResourceModel
     * @param CartRepositoryInterface $cartRepository
     * @param ReturnAuthorizer $returnAuthorizer
     * @param ReturnToken $returnToken
     * @param PaymentDetailsProvider $detailsProvider
     * @param ConfigInterface $config
     * @param StatusVisitor $statusVisitor
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        QuoteIdMaskResourceModel $quoteIdMaskResourceModel,
        CartRepositoryInterface $cartRepository,
        ReturnAuthorizer $returnAuthorizer,
        ReturnToken $returnToken,
        PaymentDetailsProvider $detailsProvider,
        ConfigInterface $config,
        StatusVisitor $statusVisitor
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteIdMaskResourceModel = $quoteIdMaskResourceModel;
        $this->cartRepository = $cartRepository;
        $this->returnAuthorizer = $returnAuthorizer;
        $this->returnToken = $returnToken;
        $this->detailsProvider = $detailsProvider;
        $this->config = $config;
        $this->statusVisitor = $statusVisitor;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        $orderNumber = (string)($args['order_number'] ?? '');
        [$vippsQuote, $quote] = $this->returnAuthorizer->authorize(
            $orderNumber,
            (string)($args['token'] ?? ''),
            $context
        );

        // Only a payment that can no longer complete gives its cart back. Reactivating the cart of a live
        // payment would let it be changed between authorisation and order placement.
        $transaction = $this->detailsProvider->get($orderNumber);
        $quoteCouldBeRestored = $transaction && $this->statusVisitor->isRestorable($transaction);
        if (!$quoteCouldBeRestored || !$this->config->getValue('cancellation_cart_persistence')) {
            return null;
        }

        $quoteIdMask = $this->quoteIdMaskFactory->create();
        $this->quoteIdMaskResourceModel->load($quoteIdMask, $quote->getId(), 'quote_id');
        if (!$quoteIdMask->getMaskedId()) {
            return null;
        }

        // Claimed before the cart is touched, so of two simultaneous calls only one goes on.
        if (!$this->returnToken->consume($vippsQuote)) {
            return null;
        }

        $quote->setIsActive(true);
        // The next payment reuses a reserved ID that is still set, and Vipps/MobilePay rejects a repeated reference.
        $quote->setReservedOrderId(null);
        $this->cartRepository->save($quote);

        return $quoteIdMask->getMaskedId();
    }
}
