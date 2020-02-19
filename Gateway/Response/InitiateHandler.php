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

namespace Vipps\Payment\Gateway\Response;

use Magento\Checkout\Helper\Data as CheckoutHelper;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Customer\Model\Session;
use Magento\Framework\{App\ResourceConnection, Session\SessionManagerInterface};
use Magento\Payment\Gateway\{Data\PaymentDataObjectInterface, Http\Transfer, Response\HandlerInterface};
use Magento\Quote\{Api\CartRepositoryInterface, Model\Quote\Payment};
use Vipps\Payment\{Api\Data\QuoteInterface,
    Gateway\Request\SubjectReader,
    Model\QuoteFactory,
    Model\QuoteRepository};

/**
 * Class InitiateHandler
 * @package Vipps\Payment\Gateway\Response
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class InitiateHandler implements HandlerInterface
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var CheckoutHelper
     */
    private $checkoutHelper;

    /**
     * @var SessionManagerInterface|Session
     */
    private $customerSession;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * InitiateHandler constructor.
     *
     * @param CartRepositoryInterface $cartRepository
     * @param SubjectReader $subjectReader
     * @param CheckoutHelper $checkoutHelper
     * @param SessionManagerInterface $customerSession
     * @param ResourceConnection $resourceConnection
     * @param QuoteFactory $quoteFactory
     * @param QuoteRepository $quoteRepository
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        SubjectReader $subjectReader,
        CheckoutHelper $checkoutHelper,
        SessionManagerInterface $customerSession,
        ResourceConnection $resourceConnection,
        QuoteFactory $quoteFactory,
        QuoteRepository $quoteRepository
    ) {
        $this->cartRepository = $cartRepository;
        $this->subjectReader = $subjectReader;
        $this->checkoutHelper = $checkoutHelper;
        $this->customerSession = $customerSession;
        $this->resourceConnection = $resourceConnection;
        $this->quoteFactory = $quoteFactory;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Save quote payment method.
     *
     * @param array $handlingSubject
     * @param array $responseBody
     *
     * @throws \Exception
     */
    public function handle(array $handlingSubject, array $responseBody) //@codingStandardsIgnoreLine
    {
        /** @var Transfer $transfer */
        $transfer = $handlingSubject['transferObject'];
        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $this->subjectReader->readPayment($handlingSubject);
        /** @var Payment $payment */
        $payment = $paymentDO->getPayment();
        $quote = $payment->getQuote();

        if (!$quote->getCheckoutMethod()) {
            if ($this->customerSession->isLoggedIn()) {
                $quote->setCheckoutMethod(Onepage::METHOD_CUSTOMER);
            } elseif ($this->checkoutHelper->isAllowedGuestCheckout($quote)) {
                $quote->setCheckoutMethod(Onepage::METHOD_GUEST);
            } else {
                $quote->setCheckoutMethod(Onepage::METHOD_REGISTER);
            }
        }
        $payment->setMethod('vipps');
        $quote->setIsActive(false);

        $connection = $this->resourceConnection->getConnection();

        try {
            $connection->beginTransaction();

            $this->cartRepository->save($quote);

            /** @var QuoteInterface $vippsQuote */
            $vippsQuote = $this->quoteFactory->create();
            $vippsQuote->setQuoteId($quote->getId());
            $vippsQuote->setStoreId($quote->getStoreId());
            $vippsQuote->setStatus(QuoteInterface::STATUS_PENDING);
            $vippsQuote->setReservedOrderId($quote->getReservedOrderId());
            $vippsQuote->setAuthToken($transfer->getBody()['merchantInfo']['authToken'] ?? '');
            $this->quoteRepository->save($vippsQuote);

            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }
    }
}
