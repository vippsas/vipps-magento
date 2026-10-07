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

namespace Vipps\Payment\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Model\Checkout\LastOrderSession;
use Vipps\Payment\Model\Express\CartRestoreCookie;
use Vipps\Payment\Model\Express\LivePaymentGuard;
use Vipps\Payment\Model\Express\LivePaymentMessages;

/**
 * Called in the background by the cart restore script: places the order if the shopper already approved
 * the payment. Unapproved payments are left alone; page loads never wait on Vipps/MobilePay.
 */
class CheckReturn implements HttpPostActionInterface
{
    public function __construct(
        private readonly ResultFactory $resultFactory,
        private readonly Session $checkoutSession,
        private readonly LivePaymentGuard $livePaymentGuard,
        private readonly LastOrderSession $lastOrderSession,
        private readonly LivePaymentMessages $livePaymentMessages,
        private readonly CartRestoreCookie $cartRestoreCookie,
        private readonly ManagerInterface $messageManager,
        private readonly UrlInterface $urlBuilder,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $quoteId = (int)$this->checkoutSession->getQuoteId();
            $decision = $quoteId ? $this->livePaymentGuard->placeAuthorised($quoteId) : null;

            if ($decision && $decision->isOrderPlaced()) {
                $this->lastOrderSession->store($decision->getOrder());
                $this->cartRestoreCookie->clearPending();
                $this->messageManager->addSuccessMessage($this->livePaymentMessages->orderPlaced());

                return $result->setData([
                    'orderPlaced' => true,
                    'redirectUrl' => $this->urlBuilder->getUrl('checkout/onepage/success'),
                ]);
            }

            if ($decision && $decision->isAuthorisedPending()) {
                $this->messageManager->addNoticeMessage($this->livePaymentMessages->authorisedPending());

                return $result->setData(['orderPlaced' => false, 'pending' => true]);
            }
        } catch (\Throwable $t) {
            $this->logger->error($t->getMessage());
        }

        return $result->setData(['orderPlaced' => false]);
    }
}
