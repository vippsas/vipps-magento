<?php declare(strict_types=1);

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

namespace Vipps\Payment\GatewayEpayment\Response\Payment;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Http\Transfer;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Vipps\Payment\Api\Data\QuoteInterface;
use Vipps\Payment\Gateway\Request\SubjectReader;
use Vipps\Payment\Model\QuoteFactory;
use Vipps\Payment\Model\QuoteRepository;

/**
 * Class InitiateHandler
 * @package Vipps\Payment\Gateway\Response
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class PostHandler implements HandlerInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * InitiateHandler constructor.
     *
     * @param SubjectReader $subjectReader
     * @param QuoteFactory $quoteFactory
     * @param QuoteRepository $quoteRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        SubjectReader            $subjectReader,
        QuoteFactory             $quoteFactory,
        QuoteRepository          $quoteRepository,
        OrderRepositoryInterface $orderRepository,
        CartRepositoryInterface  $cartRepository
    ) {
        $this->subjectReader = $subjectReader;
        $this->quoteFactory = $quoteFactory;
        $this->quoteRepository = $quoteRepository;
        $this->orderRepository = $orderRepository;
        $this->cartRepository = $cartRepository;
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

        $payment = $paymentDO->getPayment();
        $orderAdapter = $paymentDO->getOrder();

        if ($payment instanceof OrderPayment) {
            $order = $this->orderRepository->get($orderAdapter->getId());
            $quoteId = $order->getQuoteId();
        } elseif ($payment instanceof QuotePayment) {
            $cart = $payment->getQuote();
            $this->cartRepository->save($cart);
            $quoteId = $cart->getId();
        }

        /** @var QuoteInterface $vippsQuote */
        $vippsQuote = $this->quoteFactory->create();
        $vippsQuote->setStoreId($orderAdapter->getStoreId());
        $vippsQuote->setQuoteId((int)$quoteId);
        $vippsQuote->setStatus(QuoteInterface::STATUS_NEW);
        $vippsQuote->setReservedOrderId($orderAdapter->getOrderIncrementId());
        $vippsQuote->setAuthToken($transfer->getBody()['merchantInfo']['callbackAuthorizationToken'] ?? '');
        $this->quoteRepository->save($vippsQuote);
    }
}
