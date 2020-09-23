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
namespace Vipps\Payment\Ui\Component\Profiling\Column;

use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Class FormattedValue
 * @package Vipps\Payment\Ui\Component\Profiling\Column
 */
class FormattedValue extends Column
{
    /**
     * How many characters should be shown
     *
     * @var int
     */
    private static $charactersLimitToShow = 200;

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (strlen($item[$this->getData('name')]) > static::$charactersLimitToShow) {
                    $item[$this->getData('name')] =
                        substr($item[$this->getData('name')], 0, static::$charactersLimitToShow) . ' ...';
                }
            }
        }

        return $dataSource;
    }
}
