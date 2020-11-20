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
namespace Vipps\Payment\Gateway\Command;

use Magento\Payment\Helper\Formatter;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Gateway\Validator\ValidatorInterface;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Json\DecoderInterface;
use Vipps\Payment\Gateway\Exception\ExceptionFactory;
use Vipps\Payment\Gateway\Exception\VippsException;
use Vipps\Payment\Gateway\Request\SubjectReader;
use Vipps\Payment\Gateway\Transaction\Transaction;
use Vipps\Payment\Gateway\Transaction\TransactionSummary;
use Vipps\Payment\Gateway\Transaction\TransactionLogHistory\Item as TransactionLogHistoryItem;
use Vipps\Payment\Model\Profiling\ProfilerInterface;
use Vipps\Payment\Model\Order\PartialVoid\Config;
use Psr\Log\LoggerInterface;

/**
 * Class CancelCommand
 * @package Vipps\Payment\Gateway\Command
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CancelCommand extends GatewayCommand
{
    use Formatter;

    /**
     * @var BuilderInterface
     */
    private $requestBuilder;

    /**
     * @var TransferFactoryInterface
     */
    private $transferFactory;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var HandlerInterface
     */
    private $handler;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ExceptionFactory
     */
    private $exceptionFactory;

    /**
     * @var DecoderInterface
     */
    private $jsonDecoder;

    /**
     * @var ProfilerInterface
     */
    private $profiler;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var PaymentDetailsProvider
     */
    private $paymentDetailsProvider;

    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * CancelCommand constructor.
     *
     * @param Config $config
     * @param BuilderInterface $requestBuilder
     * @param TransferFactoryInterface $transferFactory
     * @param ClientInterface $client
     * @param LoggerInterface $logger
     * @param ExceptionFactory $exceptionFactory
     * @param DecoderInterface $jsonDecoder
     * @param ProfilerInterface $profiler
     * @param PaymentDetailsProvider $paymentDetailsProvider
     * @param SubjectReader $subjectReader
     * @param HandlerInterface|null $handler
     * @param ValidatorInterface|null $validator
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Config $config,
        BuilderInterface $requestBuilder,
        TransferFactoryInterface $transferFactory,
        ClientInterface $client,
        LoggerInterface $logger,
        ExceptionFactory $exceptionFactory,
        DecoderInterface $jsonDecoder,
        ProfilerInterface $profiler,
        PaymentDetailsProvider $paymentDetailsProvider,
        SubjectReader $subjectReader,
        HandlerInterface $handler = null,
        ValidatorInterface $validator = null
    ) {
        parent::__construct(
            $requestBuilder,
            $transferFactory,
            $client,
            $logger,
            $exceptionFactory,
            $jsonDecoder,
            $profiler,
            $handler,
            $validator
        );
        $this->config = $config;
        $this->requestBuilder = $requestBuilder;
        $this->transferFactory = $transferFactory;
        $this->client = $client;
        $this->handler = $handler;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->exceptionFactory = $exceptionFactory;
        $this->jsonDecoder = $jsonDecoder;
        $this->profiler = $profiler;
        $this->paymentDetailsProvider = $paymentDetailsProvider;
        $this->subjectReader = $subjectReader;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $commandSubject
     *
     * @return array|bool|ResultInterface|null
     * @throws ClientException
     * @throws ConverterException
     * @throws LocalizedException
     * @throws VippsException
     */
    public function execute(array $commandSubject)
    {
        $orderId = $this->subjectReader->readPayment($commandSubject)->getOrder()->getOrderIncrementId();
        $transaction = $this->paymentDetailsProvider->get($orderId);

        // try to cancel based on payment details data
        if ($this->cancelBasedOnPaymentDetails($commandSubject, $transaction)) {
            return true;
        }

        $offlineVoidEnabled = $this->config->isOfflinePartialVoidEnabled();
        if ($transaction->getTransactionSummary()->getCapturedAmount() > 0) {
            if (!$offlineVoidEnabled) {
                throw new LocalizedException(__('Can\'t cancel captured transaction.'));
            }

            return true;
        }

        // if previous cancel was failed - use the same request id
        $requestId = $this->getLastFailedRequestId($transaction);
        if ($requestId) {
            $commandSubject['requestId'] = $requestId;
        }

        return parent::execute($commandSubject);
    }

    /**
     * Try to cancel based on GetPaymentDetails service.
     *
     * @param $commandSubject
     * @param Transaction $transaction
     *
     * @return bool
     */
    private function cancelBasedOnPaymentDetails($commandSubject, Transaction $transaction)
    {
        $payment = $this->subjectReader->readPayment($commandSubject);

        $orderAdapter = $payment->getOrder();
        $orderIncrementId = $orderAdapter->getOrderIncrementId();

        $item = $this->findLatestSuccessHistoryItem($transaction);
        if ($item) {
            $responseBody = $this->prepareResponseBody($transaction, $item, $orderIncrementId);
            if ($this->handler) {
                $this->handler->handle($commandSubject, $responseBody);
            }
            return true;
        }

        return false;
    }

    /**
     * Prepare response body based of GetPaymentDetails service data.
     *
     * @param Transaction $transaction
     * @param TransactionLogHistoryItem $item
     * @param $orderId
     *
     * @return array|null
     */
    private function prepareResponseBody(Transaction $transaction, TransactionLogHistoryItem $item, $orderId)
    {
        return [
            'orderId' => $orderId,
            'transactionInfo' => [
                'amount' => $item->getAmount(),
                'status' => Transaction::TRANSACTION_STATUS_CANCELLED,
                "timeStamp" => $item->getTimeStamp(),
                "transactionId" => $item->getTransactionId(),
                "transactionText" => $item->getTransactionText()
            ],
            'transactionSummary' => $transaction->getTransactionSummary()->toArray(
                [
                    TransactionSummary::CAPTURED_AMOUNT,
                    TransactionSummary::REMAINING_AMOUNT_TO_CAPTURE,
                    TransactionSummary::REFUNDED_AMOUNT,
                    TransactionSummary::REMAINING_AMOUNT_TO_REFUND
                ]
            )
        ];
    }

    /**
     * Get latest successful transaction log history item.
     *
     * @param Transaction $transaction
     *
     * @return null|TransactionLogHistoryItem
     */
    private function findLatestSuccessHistoryItem(Transaction $transaction)
    {
        foreach ($transaction->getTransactionLogHistory()->getItems() as $item) {
            $inContext = in_array(
                $item->getOperation(),
                [
                    Transaction::TRANSACTION_OPERATION_CANCEL,
                    Transaction::TRANSACTION_OPERATION_VOID
                ]
            );

            if ($inContext && $item->isOperationSuccess()) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Retrieve request id of last failed operation from transaction log history.
     *
     * @param Transaction $transaction
     *
     * @return string|null
     */
    private function getLastFailedRequestId(Transaction $transaction)
    {
        foreach ($transaction->getTransactionLogHistory()->getItems() as $item) {
            $inContext = in_array(
                $item->getOperation(),
                [
                    Transaction::TRANSACTION_OPERATION_CANCEL,
                    Transaction::TRANSACTION_OPERATION_VOID
                ]
            );

            if (!$inContext) {
                continue;
            }
            if (true !== $item->isOperationSuccess()) {
                return $item->getRequestId();
            }
        }
        return null;
    }
}
