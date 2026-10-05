<?php
/**
 * Copyright 2026 Vipps
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
declare(strict_types=1);

namespace Vipps\Payment\Plugin\Controller\Payment;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Vipps\Payment\Controller\Payment\ShippingDetails;
use Vipps\Payment\Model\Express\ShippingCallbackGuard;

/**
 * Authenticates the shipping details callback ahead of every other plugin on it.
 *
 * Integrations replace ShippingDetails::execute() with their own around plugin to offer extra shipping
 * options, and one that never calls $proceed skips any check inside the controller. Declared with the lowest
 * sort order, this plugin wraps all of them, so an unauthenticated request is rejected before any of them
 * can load or change the cart.
 */
class ShippingDetailsAuthorization
{
    /**
     * @var ShippingCallbackGuard
     */
    private $guard;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var ResultFactory
     */
    private $resultFactory;

    /**
     * @param ShippingCallbackGuard $guard
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     */
    public function __construct(
        ShippingCallbackGuard $guard,
        RequestInterface $request,
        ResultFactory $resultFactory
    ) {
        $this->guard = $guard;
        $this->request = $request;
        $this->resultFactory = $resultFactory;
    }

    /**
     * @param ShippingDetails $subject
     * @param callable $proceed
     *
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(ShippingDetails $subject, callable $proceed)
    {
        $status = $this->guard->getRejectionStatus($this->request);
        if ($status === null) {
            return $proceed();
        }

        /** @var ResultInterface $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setHttpResponseCode($status);
        $result->setData([
            'status' => $status,
            'message' => __('Invalid request')
        ]);

        return $result;
    }
}
