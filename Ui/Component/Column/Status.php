<?php
/**
 * Copyright 2020 Vipps
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

namespace Vipps\Payment\Ui\Component\Column;

use Magento\Framework\Data\OptionSourceInterface;
use Vipps\Payment\Api\Data\QuoteStatusInterface;

/**
 * Class Status
 * @package Vipps\Payment\Ui\Component\Column
 */
class Status implements OptionSourceInterface
{
    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        $array = [];
        foreach ($this->getOptions() as $key => $val) {
            $array[] = [
                'value' => $key,
                'label' => $val
            ];
        }

        return $array;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return [
            QuoteStatusInterface::STATUS_NEW            => __('New'),
            QuoteStatusInterface::STATUS_PENDING        => __('Pending'),
            QuoteStatusInterface::STATUS_CANCELED       => __('Canceled'),
            QuoteStatusInterface::STATUS_EXPIRED        => __('Expired'),
            QuoteStatusInterface::STATUS_RESERVED       => __('Reserved'),
            QuoteStatusInterface::STATUS_RESERVE_FAILED => __('Reserve Failed'),
            QuoteStatusInterface::STATUS_REVERTED       => __('Reverted'),
            QuoteStatusInterface::STATUS_REVERT_FAILED  => __('Revert Failed')
        ];
    }

    /**
     * @param string $code
     * @return string
     */
    public function getLabel($code)
    {
        $options = $this->getOptions();

        return $options[$code] ?? $code;
    }
}
