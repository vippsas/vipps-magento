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

/**
 * Class Position
 * @package Vipps\Payment\Model\Source
 */
class Position
{
    /**
     * Positions source getter for Home Page
     *
     * @return array
     */
    public function getPositionsHP()
    {
        return [
            '0' => __('Header (center)'),
            '1' => __('Sidebar (right)')
        ];
    }

    /**
     * Positions source getter for Catalog Category Page
     *
     * @return array
     */
    public function getPositionsCCP()
    {
        return [
            '0' => __('Header (center)'),
            '1' => __('Sidebar (right)')
        ];
    }

    /**
     * Positions source getter for Catalog Product Page
     *
     * @return array
     */
    public function getPositionsCPP()
    {
        return [
            '0' => __('Header (center)'),
            '1' => __('Near Add to cart button')
        ];
    }

    /**
     * Positions source getter for Checkout Cart Page
     *
     * @return array
     */
    public function getPositionsCheckout()
    {
        return [
            '0' => __('Header (center)'),
            '1' => __('After Proceed to checkout button')
        ];
    }
}
