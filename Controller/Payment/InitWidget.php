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

use Magento\Checkout\Helper\Data as CheckoutHelper;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\CommandManagerInterface;
use Vipps\Payment\GatewayEpayment\Config\Config;
use Vipps\Payment\GatewayEpayment\Request\InitSession\InitiateBuilderInterface;
use Vipps\Payment\Model\Checkout\LastOrderSession;
use Vipps\Payment\Model\Express\CartRestoreCookie;
use Vipps\Payment\Model\Express\LivePaymentDecision;
use Vipps\Payment\Model\Express\LivePaymentGuard;
use Vipps\Payment\Model\Express\LivePaymentMessages;
use Vipps\Payment\Model\Method\Vipps;

/**
 * Creates a Vipps ePayment session for the current quote, returning the payment URL as JSON
 * for the Widget SDK trigger's async callback (unlike InitExpress's server-side redirect).
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class InitWidget implements ActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly ResultFactory $resultFactory,
        private readonly SessionManagerInterface $checkoutSession,
        private readonly SessionManagerInterface $customerSession,
        private readonly CommandManagerInterface $commandManager,
        private readonly Config $config,
        private readonly CheckoutHelper $checkoutHelper,
        private readonly LoggerInterface $logger,
        private readonly CartRestoreCookie $cartRestoreCookie,
        private readonly LivePaymentGuard $livePaymentGuard,
        private readonly LastOrderSession $lastOrderSession,
        private readonly LivePaymentMessages $livePaymentMessages,
        private readonly ManagerInterface $messageManager,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            if (!$this->config->getValue('widget_sdk_enabled') || !$this->config->getValue('express_checkout')) {
                throw new LocalizedException(__('Widget SDK is not available.'));
            }

            $decision = $this->initiatePayment();

            // The widget navigates to redirectUrl itself instead of opening a new payment.
            if ($decision->isOrderPlaced()) {
                $this->lastOrderSession->store($decision->getOrder());
                $this->cartRestoreCookie->clearPending();
                $this->messageManager->addSuccessMessage($this->livePaymentMessages->orderPlaced());

                return $result->setData(['redirectUrl' => $this->urlBuilder->getUrl('checkout/onepage/success')]);
            }

            if ($decision->isAuthorisedPending()) {
                $this->messageManager->addNoticeMessage($this->livePaymentMessages->authorisedPending());

                return $result->setData(['redirectUrl' => $this->urlBuilder->getUrl('checkout/cart')]);
            }

            $responseData = $decision->getPaymentResponse();
            $reference = $responseData['reference'] ?? $this->checkoutSession->getQuote()->getReservedOrderId();

            $quoteId = $this->checkoutSession->getQuoteId();
            if ($quoteId) {
                $this->cartRestoreCookie->setPending((int)$quoteId);
            }

            $this->checkoutSession->clearStorage();

            // The SDK may load the return URL in its cookie-less iframe; the widget script repeats it in the
            // main tab so Fallback runs in the shopper's session.
            return $result->setData([
                'paymentUrl' => $responseData['redirectUrl'],
                'returnUrl' => $this->urlBuilder->getUrl('vipps/payment/fallback', ['reference' => $reference]),
            ]);
        } catch (LocalizedException $e) {
            $this->logger->critical($this->enlargeMessage($e));

            return $result->setHttpResponseCode(400)->setData(['message' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->logger->critical($this->enlargeMessage($e));

            return $result->setHttpResponseCode(400)->setData([
                'message' => (string)__(
                    'An error occurred during request to %1. Please try again later.',
                    $this->config->getTitle()
                ),
            ]);
        }
    }

    /**
     * @return \Magento\Payment\Gateway\Command\ResultInterface|null
     * @throws LocalizedException
     */
    private function initiatePayment(): LivePaymentDecision
    {
        $quote = $this->checkoutSession->getQuote();

        $this->setCheckoutMethod($quote);
        if ($quote->getCheckoutMethod() === Onepage::METHOD_REGISTER) {
            throw new LocalizedException(__('Sorry, guest checkout is not available.'));
        }

        return $this->livePaymentGuard->initiate(
            $quote->getId() ? (int)$quote->getId() : null,
            function () use ($quote) {
                $shippingAddress = $quote->getShippingAddress();
                $shippingAddress->setShippingMethod('');
                $quote->collectTotals();

                $quote->setIsActive(false);

                return $this->commandManager->initiatePayment(
                    $quote->getPayment(),
                    [
                        'amount' => $quote->getGrandTotal(),
                        InitiateBuilderInterface::PAYMENT_TYPE_KEY =>
                            InitiateBuilderInterface::PAYMENT_TYPE_EXPRESS_CHECKOUT,
                        Vipps::METHOD_TYPE_KEY => Vipps::METHOD_TYPE_EXPRESS_CHECKOUT,
                    ]
                );
            }
        );
    }

    /**
     * @param $quote
     */
    private function setCheckoutMethod($quote): void
    {
        if (!$quote->getCheckoutMethod(true)) {
            if ($this->customerSession->isLoggedIn()) {
                $quote->setCheckoutMethod(Onepage::METHOD_CUSTOMER);
            } elseif ($this->checkoutHelper->isAllowedGuestCheckout($quote)) {
                $quote->setCheckoutMethod(Onepage::METHOD_GUEST);
            } else {
                $quote->setCheckoutMethod(Onepage::METHOD_REGISTER);
            }
        }
    }

    /**
     * @param $e \Exception
     *
     * @return string
     */
    private function enlargeMessage($e): string
    {
        $quoteId = $this->checkoutSession->getQuoteId();
        $trace = $e->getTraceAsString();
        $message = $e->getMessage();

        return "QuoteID: $quoteId. Exception message: $message. Stack Trace $trace";
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
