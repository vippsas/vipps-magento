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

namespace Vipps\Payment\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Vipps\Payment\Model\Express\CartRestorer;
use Vipps\Payment\Model\Express\CartRestoreCookie;

class CartRestoreObserver implements ObserverInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly CartRestoreCookie $cartRestoreCookie,
        private readonly CartRestorer $cartRestorer
    ) {
    }

    public function execute(Observer $observer): void
    {
        if ($this->request->getModuleName() !== 'checkout' || $this->request->getControllerName() !== 'cart') {
            return;
        }

        $quoteId = $this->cartRestoreCookie->getPending();
        if (!$quoteId) {
            return;
        }

        $this->cartRestoreCookie->clearPending();

        if ($this->cartRestorer->restore($quoteId)) {
            $this->cartRestoreCookie->markRestored();
        }
    }
}
