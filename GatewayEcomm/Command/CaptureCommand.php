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
namespace Vipps\Payment\GatewayEcomm\Command;

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
use Vipps\Payment\GatewayEcomm\Data\Payment;
use Vipps\Payment\GatewayEcomm\Data\PaymentEventLog;
use Vipps\Payment\GatewayEcomm\Exception\VippsException;
use Vipps\Payment\GatewayEcomm\Request\SubjectReader;
use Vipps\Payment\Model\PaymentEventLogProvider;
use Vipps\Payment\Model\PaymentProvider;
use Vipps\Payment\Model\Profiling\ProfilerInterface;

/**
 * Class CaptureCommand
 * @package Vipps\Payment\GatewayEcomm\Command
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
        return parent::execute($commandSubject);
    }
}
