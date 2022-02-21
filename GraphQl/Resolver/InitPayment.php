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
use Magento\Sales\Api\OrderRepositoryInterface;
use Vipps\Payment\Api\CommandManagerInterface;
use Vipps\Payment\Gateway\Request\Initiate\InitiateBuilderInterface;
use Vipps\Payment\Model\OrderLocator;

class InitPayment implements ResolverInterface
{
    /**
     * @var CommandManagerInterface
     */
    private $commandManager;
    
    /**
     * @var OrderLocator
     */
    private $orderLocator;

    /**
     * InitPayment constructor.
     *
     * @param CommandManagerInterface $commandManager
     * @param OrderLocator $orderLocator
     */
    public function __construct(
        CommandManagerInterface $commandManager,
        OrderLocator $orderLocator
    ) {
        $this->commandManager = $commandManager;
        $this->orderLocator = $orderLocator;
    }
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $responseData = [];

        $incrementId = $args['input']['order_number'] ?? null;
        $fallbackUrl = $args['input']['fallback_url'] ?? null;
        if ($incrementId) {
            $order = $this->orderLocator->get($incrementId);
            if ($order) {
                $responseData = $this->commandManager->initiatePayment(
                    $order->getPayment(),
                    [
                        'amount' => $order->getGrandTotal(),
                        InitiateBuilderInterface::PAYMENT_TYPE_KEY =>
                            InitiateBuilderInterface::PAYMENT_TYPE_REGULAR_PAYMENT,
                        'fallback_url' => $fallbackUrl
                    ]
                );
            }
        }

        return ['url' => $responseData['url']];
    }
}
