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

use Magento\Sales\Api\OrderRepositoryInterface;
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
use Vipps\Payment\Model\Profiling\ProfilerInterface;
use Vipps\Payment\Gateway\Exception\VippsException;
use Vipps\Payment\Gateway\Request\SubjectReader;
use Vipps\Payment\Gateway\Transaction\Transaction;
use Vipps\Payment\Gateway\Transaction\TransactionSummary;
use Vipps\Payment\Gateway\Transaction\TransactionLogHistory\Item as TransactionLogHistoryItem;
use Vipps\Payment\Gateway\Exception\ExceptionFactory;
use Psr\Log\LoggerInterface;

/**
 * Class CaptureCommand
 * @package Vipps\Payment\Gateway\Command
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CaptureCommand extends GatewayCommand
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
     * @var PaymentDetailsProvider
     */
    private $paymentDetailsProvider;

    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * CaptureCommand constructor.
     *
     * @param BuilderInterface $requestBuilder
     * @param TransferFactoryInterface $transferFactory
     * @param ClientInterface $client
     * @param LoggerInterface $logger
     * @param ExceptionFactory $exceptionFactory
     * @param DecoderInterface $jsonDecoder
     * @param ProfilerInterface $profiler
     * @param PaymentDetailsProvider $paymentDetailsProvider
     * @param SubjectReader $subjectReader
     * @param OrderRepositoryInterface $orderRepository
     * @param HandlerInterface|null $handler
     * @param ValidatorInterface|null $validator
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        BuilderInterface $requestBuilder,
        TransferFactoryInterface $transferFactory,
        ClientInterface $client,
        LoggerInterface $logger,
        ExceptionFactory $exceptionFactory,
        DecoderInterface $jsonDecoder,
        ProfilerInterface $profiler,
        PaymentDetailsProvider $paymentDetailsProvider,
        SubjectReader $subjectReader,
        OrderRepositoryInterface $orderRepository,
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
        $this->orderRepository = $orderRepository;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $commandSubject
     *
     * @return ResultInterface|array|bool|null
     * @throws ClientException
     * @throws ConverterException
     * @throws LocalizedException
     * @throws VippsException
     */
    public function execute(array $commandSubject)
    {
        $amount = $this->subjectReader->readAmount($commandSubject);
        $amount = (int)round($this->formatPrice($amount) * 100);

        if ($amount === 0) {
            return true;
        }

        $orderId = $this->subjectReader->readPayment($commandSubject)->getOrder()->getOrderIncrementId();
        $transaction = $this->paymentDetailsProvider->get($orderId);

        // try to capture based on payment details data
        if ($this->captureBasedOnPaymentDetails($commandSubject, $transaction)) {
            return true;
        }

        // try to capture based on capture service itself
        if ($transaction->getTransactionSummary()->getRemainingAmountToCapture() < $amount) {
            throw new LocalizedException(__('Captured amount is higher then remaining amount to capture'));
        }

        $requestId = $this->getLastFailedRequestId($transaction, $amount);
        if ($requestId) {
            $commandSubject['requestId'] = $requestId;
        }

        return parent::execute($commandSubject);
    }

    /**
     * Try to capture based on GetPaymentDetails service.
     *
     * @param $commandSubject
     * @param Transaction $transaction
     *
     * @return bool
     * @throws LocalizedException
     */
    private function captureBasedOnPaymentDetails($commandSubject, Transaction $transaction)
    {
        $payment = $this->subjectReader->readPayment($commandSubject);
        $amount = $this->subjectReader->readAmount($commandSubject);
        $amount = (int)round($this->formatPrice($amount) * 100);

        $orderAdapter = $payment->getOrder();
        $orderIncrementId = $orderAdapter->getOrderIncrementId();

        if ($orderAdapter->getId() === null) {
            return false;
        }
        $order = $this->orderRepository->get($orderAdapter->getId());

        $magentoTotalDue = (int)round($this->formatPrice($order->getTotalDue()) * 100);
        $vippsTotalDue = $transaction->getTransactionSummary()->getRemainingAmountToCapture();

        $deltaTotalDue = $magentoTotalDue - $vippsTotalDue;
        if ($deltaTotalDue > 0) {
            // In means that in Vipps the remainingAmountToCapture is less then in Magento
            // It can happened if previous operation was successful in vipps
            // but for some reason Magento didn't get response

            // Check that we are trying to capture the same amount as has been already captured in Vipps
            // otherwise - show an error about desync
            if ((int)$amount === (int)$deltaTotalDue) {
                //prepare capture response based on data from getPaymentDetails service
                $responseBody = $this->prepareResponseBody($transaction, $amount, $orderIncrementId);
                if (!is_array($responseBody)) {
                    throw new LocalizedException(__('An error occurred during capture info sync.'));
                }
                if ($this->handler) {
                    $this->handler->handle($commandSubject, $responseBody);
                }
                return true;
            } else {
                $suggestedAmountToCapture = $this->formatPrice($deltaTotalDue / 100);
                $message = __(
                    'Captured amount is not the same as you are trying to capture.'
                    . PHP_EOL . ' Payment information was not synced correctly between Magento and Vipps.'
                    . PHP_EOL . ' It might be that the previous operation was successfully completed in Vipps'
                    . PHP_EOL . ' but Magento did not receive a response.'
                    . PHP_EOL . ' To be in sync you have to capture the same amount that has been already captured'
                    . PHP_EOL . ' in Vipps: %1',
                    $suggestedAmountToCapture
                );

                throw new LocalizedException($message);
            }
        }

        return false;
    }

    /**
     * Prepare response body based of GetPaymentDetails service data.
     *
     * @param Transaction $transaction
     * @param $amount
     * @param $orderId
     *
     * @return array|null
     */
    private function prepareResponseBody(Transaction $transaction, $amount, $orderId)
    {
        $item = $this->findLatestSuccessHistoryItem($transaction, $amount);
        if ($item) {
            return [
                'orderId' => $orderId,
                'transactionInfo' => [
                    'amount' => $item->getAmount(),
                    'status' => Transaction::TRANSACTION_STATUS_CAPTURED,
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
        return null;
    }

    /**
     * Get latest successful transaction log history item.
     *
     * @param Transaction $transaction
     * @param $amount
     *
     * @return TransactionLogHistoryItem|null
     */
    private function findLatestSuccessHistoryItem(Transaction $transaction, $amount)
    {
        foreach ($transaction->getTransactionLogHistory()->getItems() as $item) {
            if ($item->getOperation() == Transaction::TRANSACTION_OPERATION_CAPTURE
                && $item->isOperationSuccess()
                && $item->getAmount() == $amount
            ) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Retrieve request id of last failed operation from transaction log history.
     *
     * @param Transaction $transaction
     * @param int $amount
     *
     * @return string|null
     */
    private function getLastFailedRequestId(Transaction $transaction, $amount)
    {
        foreach ($transaction->getTransactionLogHistory()->getItems() as $item) {
            if ($item->getOperation() != Transaction::TRANSACTION_OPERATION_CAPTURE) {
                continue;
            }
            if (true !== $item->isOperationSuccess() && $item->getAmount() == $amount) {
                return $item->getRequestId();
            }
        }
        return null;
    }
}
