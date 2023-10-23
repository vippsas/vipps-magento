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
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Vipps\Payment\Api\Payment\CommandManagerInterface;
use Vipps\Payment\Gateway\Request\Initiate\InitiateBuilderInterface;

class InitPayment implements ResolverInterface
{
    /**
     * @var CommandManagerInterface
     */
    private $commandManager;

    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedCartId;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * InitPayment constructor.
     *
     * @param CommandManagerInterface $commandManager
     * @param MaskedQuoteIdToQuoteIdInterface $maskedCartId
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        CommandManagerInterface $commandManager,
        MaskedQuoteIdToQuoteIdInterface $maskedCartId,
        CartRepositoryInterface $cartRepository
    ) {
        $this->commandManager = $commandManager;
        $this->maskedCartId = $maskedCartId;
        $this->cartRepository = $cartRepository;
    }
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $redirectUrl = null;

        $maskedCartId = $args['input']['cart_id'] ?? '';
        $fallbackUrl = $args['input']['fallback_url'] ?? '';

        $cartId = $this->maskedCartId->execute($maskedCartId);
        if ($cartId) {
            $quote = $this->cartRepository->get($cartId);
            $responseData = $this->commandManager->initiatePayment(
                $quote->getPayment(),
                [
                    'amount' => $quote->getGrandTotal(),
                    InitiateBuilderInterface::PAYMENT_TYPE_KEY =>
                        InitiateBuilderInterface::PAYMENT_TYPE_REGULAR_PAYMENT,
                    'fallback_url' => $fallbackUrl
                ]
            );

            $redirectUrl = $responseData['url'] ?? null;
        }

        return ['url' => $redirectUrl];
    }
}
