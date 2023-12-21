<?php
/**
 * Copyright 2022 Vipps
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
 * Class RefundCommand
 * @package Vipps\Payment\GatewayEpayment\Command
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RefundCommand extends GatewayCommand
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
        HandlerInterface $handler = null,
        ValidatorInterface $validator = null
    ) {
        parent::__construct($requestBuilder, $transferFactory, $client, $logger, $jsonDecoder, $profiler, $handler, $validator);
        $this->paymentProvider = $paymentProvider;
        $this->paymentEventLogProvider = $paymentEventLogProvider;
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
        $payment = $this->paymentProvider->get($orderId);

        // try to refund based on payment info
        if ($this->refundBasedOnPayment($commandSubject, $payment)) {
            return true;
        }

        // try to refund based on refund service itself
        if ($this->getRemainingAmountToRefund($payment) < $amount) {
            throw new LocalizedException(__('Refunded amount is higher then remaining amount to refund'));
        }

//        $paymentEventLog = $this->paymentEventLogProvider->get($orderId);
//        $requestId = $this->getFailedEventItem($paymentEventLog, $amount);
//        if ($requestId) {
//            $commandSubject[\Vipps\Payment\GatewayEpayment\Http\Client\ClientInterface::HEADER_PARAM_IDEMPOTENCY_KEY]
//                = $requestId;
//        }

        return parent::execute($commandSubject);
    }

    /**
     * Try to refund based on GetPaymentDetails service.
     *
     * @param $commandSubject
     * @param Payment $payment
     *
     * @return bool
     * @throws LocalizedException
     */
    private function refundBasedOnPayment($commandSubject, Payment $payment)
    {
        $paymentDO = $this->subjectReader->readPayment($commandSubject);
        if (!$paymentDO) {
            return false;
        }

        $amount = $this->subjectReader->readAmount($commandSubject);
        $amount = (int)round($this->formatPrice($amount) * 100);

        $orderAdapter = $paymentDO->getOrder();

        $order = $this->orderRepository->get($orderAdapter->getId());

        $magentoTotalRefunded = (int)round($this->formatPrice($order->getTotalRefunded()) * 100);
        $vippsTotalRefunded = $payment->getAggregate()->getRefundedAmount()->getValue();

        $deltaTotalRefunded = $vippsTotalRefunded - $magentoTotalRefunded;
        if ($deltaTotalRefunded > 0) {
            // In means that in Vipps the refunded amount is higher then in Magento
            // It can happened if previous operation was successful in vipps
            // but for some reason Magento didn't get response

            // Check that we are trying to refund the same amount as has been already refunded in Vipps
            // otherwise - show an error about desync
            if ($amount !== $deltaTotalRefunded) {
                $suggestedAmountToRefund = $this->formatPrice($deltaTotalRefunded / 100);
                $message = __(
                    'Refunded amount is not the same as you are trying to refund.'
                    . PHP_EOL . ' Payment information was not synced correctly between Magento and Vipps.'
                    . PHP_EOL . ' It might be that the previous operation was successfully completed in Vipps'
                    . PHP_EOL . ' but Magento did not receive a response.'
                    . PHP_EOL . ' To be in sync you have to refund the same amount that has been already refunded'
                    . PHP_EOL . ' in Vipps: %1 %2',
                    $suggestedAmountToRefund,
                    $order->getStoreCurrencyCode()
                );

                throw new LocalizedException($message);
            }

            return true;
        }

        return false;
    }

    /**
     * @param Payment $payment
     *
     * @return int|null
     */
    private function getRemainingAmountToRefund(Payment $payment): ?int
    {
        return $payment->getAggregate()->getCapturedAmount()->getValue();
    }

    /**
     * Retrieve request id of last failed operation from transaction log history.
     *
     * @param PaymentEventLog $paymentEventLog
     * @param int $amount
     *
     * @return PaymentEventLog\Item|null
     */
    private function getFailedEventItem(PaymentEventLog $paymentEventLog, $amount)
    {
        foreach ($paymentEventLog->getItems() as $item) {
            if ($item->getPaymentAction() !== PaymentEventLog\Item::PAYMENT_ACTION_REFUND) {
                continue;
            }
            if (!$item->getSuccess() && $item->getAmount() === $amount) {
                return $item;
            }
        }
        return null;
    }
}
