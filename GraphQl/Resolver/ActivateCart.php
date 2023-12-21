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
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Vipps\Payment\Api\CommandManagerInterface;
use Vipps\Payment\Api\QuoteRepositoryInterface;
use Vipps\Payment\Gateway\Request\Initiate\InitiateBuilderInterface;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Vipps\Payment\Model\QuoteRepository;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResourceModel;

class ActivateCart implements ResolverInterface
{
    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;
    /**
     * @var QuoteRepositoryInterface
     */
    private $vippsQuoteRepository;
    /**
     * @var QuoteIdMaskResourceModel
     */
    private $quoteIdMaskResourceModel;
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * ActivateCart constructor.
     *
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param QuoteRepositoryInterface $vippsQuoteRepository
     * @param QuoteIdMaskResourceModel $quoteIdMaskResourceModel
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        QuoteRepositoryInterface $vippsQuoteRepository,
        QuoteIdMaskResourceModel $quoteIdMaskResourceModel,
        CartRepositoryInterface $cartRepository
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->vippsQuoteRepository = $vippsQuoteRepository;
        $this->quoteIdMaskResourceModel = $quoteIdMaskResourceModel;
        $this->cartRepository = $cartRepository;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $maskedId = null;
        $orderNumber = $args['order_number'] ?? '';

        $vippsQuote = $this->vippsQuoteRepository->loadByOrderId($orderNumber);
        if ($vippsQuote) {
            $quoteId = $vippsQuote->getQuoteId();

            $quoteIdMask = $this->quoteIdMaskFactory->create();
            $this->quoteIdMaskResourceModel->load($quoteIdMask, $quoteId, 'quote_id');
            if ($quoteIdMask->getMaskedId()) {
                $quote = $this->cartRepository->get($quoteId);
                $quote->setIsActive(true);
                $quote->setReservedOrderId(null);
                $this->cartRepository->save($quote);

                $maskedId = $quoteIdMask->getMaskedId();
            }
        }

        return $maskedId;
    }
}
