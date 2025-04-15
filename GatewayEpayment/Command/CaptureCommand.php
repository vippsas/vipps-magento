<?php
/**
 * Copyright 2023 Vipps
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
namespace Vipps\Payment\GatewayEpayment\Command;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Json\DecoderInterface;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Gateway\Validator\ValidatorInterface;
use Magento\Payment\Helper\Formatter;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Vipps\Payment\GatewayEpayment\Data\Payment;
use Vipps\Payment\GatewayEpayment\Data\PaymentEventLog;
use Vipps\Payment\GatewayEpayment\Exception\VippsException;
use Vipps\Payment\GatewayEpayment\Request\SubjectReader;
use Vipps\Payment\Model\PaymentEventLogProvider;
use Vipps\Payment\GatewayEpayment\Model\PaymentProvider;
use Vipps\Payment\Model\Profiling\ProfilerInterface;

/**
 * Class CaptureCommand
 * @package Vipps\Payment\GatewayEpayment\Command
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
     * @var DecoderInterface
     */
    private $jsonDecoder;
    /**
     * @var PaymentProvider
     */
    private $paymentProvider;
    /**
     * @var PaymentEventLogProvider
     */
    private $paymentEventLogProvider;
    /**
     * @var SubjectReader
     */
    private $subjectReader;
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    public function __construct(
        BuilderInterface $requestBuilder,
        TransferFactoryInterface $transferFactory,
        ClientInterface $client,
        LoggerInterface $logger,
        DecoderInterface $jsonDecoder,
        PaymentProvider $paymentProvider,
        PaymentEventLogProvider $paymentEventLogProvider,
        SubjectReader $subjectReader,
        OrderRepositoryInterface $orderRepository,
        ProfilerInterface $profiler,
        ?HandlerInterface $handler = null,
        ?ValidatorInterface $validator = null
    ) {
        parent::__construct($requestBuilder, $transferFactory, $client, $logger, $jsonDecoder, $profiler, $handler, $validator);
        $this->paymentProvider = $paymentProvider;
        $this->paymentEventLogProvider = $paymentEventLogProvider;
        $this->subjectReader = $subjectReader;
        $this->orderRepository = $orderRepository;
    }

    public function execute(array $commandSubject)
    {
        $amount = $this->subjectReader->readAmount($commandSubject);
        $amount = (int)round($this->formatPrice($amount) * 100);

        if ($amount === 0) {
            return true;
        }

        $orderId = $this->subjectReader->readPayment($commandSubject)->getOrder()->getOrderIncrementId();
        $payment = $this->paymentProvider->get($orderId);

        // try to capture based on payment info
        if ($this->captureBasedOnPayment($commandSubject, $payment)) {
            return true;
        }

        // try to capture based on capture service itself
        if ($this->getRemainingAmountToCapture($payment) < $amount) {
            throw new LocalizedException(__('Captured amount is higher then remaining amount to capture'));
        }

//        $paymentEventLog = $this->paymentEventLogProvider->get($orderId);
//        $requestId = $this->getFailedEventItem($paymentEventLog, $amount);
//        if ($requestId) {
//            $commandSubject[\Vipps\Checkout\Gateway\Http\Client\ClientInterface::HEADER_PARAM_IDEMPOTENCY_KEY]
//                = $requestId;
//        }

        return parent::execute($commandSubject);
    }

    /**
     * Try to capture based on GetPaymentDetails service.
     *
     * @param $commandSubject
     * @param \Vipps\Payment\GatewayEpayment\Data\Payment $payment
     *
     * @return bool
     * @throws LocalizedException
     */
    private function captureBasedOnPayment($commandSubject, Payment $payment)
    {
        $paymentDO = $this->subjectReader->readPayment($commandSubject);
        if (!$paymentDO) {
            return false;
        }

        $amount = $this->subjectReader->readAmount($commandSubject);
        $amount = (int)round($this->formatPrice($amount) * 100);

        $orderAdapter = $paymentDO->getOrder();
        $order = $this->orderRepository->get($orderAdapter->getId());

        $magentoTotalDue = (int)round($this->formatPrice($order->getTotalDue()) * 100);
        $vippsTotalDue = $this->getRemainingAmountToCapture($payment);

        $deltaTotalDue = $magentoTotalDue - $vippsTotalDue;
        if ($deltaTotalDue > 0) {
            // In means that in Vipps the remainingAmountToCapture is less then in Magento
            // It can happened if previous operation was successful in vipps
            // but for some reason Magento didn't get response

            // Check that we are trying to capture the same amount as has been already captured in Vipps
            // otherwise - show an error about desync
            if ($amount === $deltaTotalDue) {
                return true;
            }
            $suggestedAmountToCapture = $this->formatPrice($deltaTotalDue / 100);
            $message = __(
                'Captured amount is not the same as you are trying to capture.'
                . PHP_EOL . ' Payment information was not synced correctly between Magento and Vipps.'
                . PHP_EOL . ' It might be that the previous operation was successfully completed in Vipps'
                . PHP_EOL . ' but Magento did not receive a response.'
                . PHP_EOL . ' To be in sync you have to capture the same amount that has been already captured'
                . PHP_EOL . ' in Vipps: %1 %2',
                $suggestedAmountToCapture,
                $order->getStoreCurrencyCode()
            );
            throw new LocalizedException($message);
        }

        return false;
    }

    /**
     * @param Payment $payment
     *
     * @return int|null
     */
    private function getRemainingAmountToCapture(Payment $payment): ?int
    {
        return $payment->getAggregate()->getAuthorizedAmount()->getValue()
            - $payment->getAggregate()->getCancelledAmount()->getValue()
            - $payment->getAggregate()->getCapturedAmount()->getValue()
            - $payment->getAggregate()->getRefundedAmount()->getValue();
    }

    /**
     * Retrieve request id of last failed operation from transaction log history.
     *
     * @param \Vipps\Payment\GatewayEpayment\Data\PaymentEventLog $paymentEventLog
     * @param int $amount
     *
     * @return PaymentEventLog\Item|null
     */
    private function getFailedEventItem(PaymentEventLog $paymentEventLog, $amount)
    {
        foreach ($paymentEventLog->getItems() as $item) {
            if ($item->getPaymentAction() !== PaymentEventLog\Item::PAYMENT_ACTION_CAPTURE) {
                continue;
            }
            if (!$item->getSuccess() && $item->getAmount() === $amount) {
                return $item;
            }
        }
        return null;
    }
}
