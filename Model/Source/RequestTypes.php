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
namespace Vipps\Payment\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Vipps\Payment\Model\Profiling\TypeInterface;

/**
 * Class RequestTypes
 * @package Vipps\Payment\Model\Source
 */
class RequestTypes implements OptionSourceInterface
{
    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => TypeInterface::INITIATE_PAYMENT,
                'label' => TypeInterface::INITIATE_PAYMENT_LABEL,
            ],
            [
                'value' => TypeInterface::GET_PAYMENT_DETAILS,
                'label' => TypeInterface::GET_PAYMENT_DETAILS_LABEL,
            ],
            [
                'value' => TypeInterface::GET_ORDER_STATUS,
                'label' => TypeInterface::GET_ORDER_STATUS_LABEL,
            ],
            [
                'value' => TypeInterface::CAPTURE,
                'label' => TypeInterface::CAPTURE_LABEL,
            ],
            [
                'value' => TypeInterface::REFUND,
                'label' => TypeInterface::REFUND_LABEL,
            ],
            [
                'value' => TypeInterface::CANCEL,
                'label' => TypeInterface::CANCEL_LABEL,
            ],
            [
                'value' => TypeInterface::VOID,
                'label' => TypeInterface::VOID_LABEL,
            ]
        ];
    }
}
