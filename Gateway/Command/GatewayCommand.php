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

use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Gateway\Validator\ValidatorInterface;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Json\DecoderInterface;
use Vipps\Payment\Gateway\Exception\ExceptionFactory;
use Vipps\Payment\Gateway\Exception\VippsException;
use Vipps\Payment\Model\Profiling\ProfilerInterface;
use Laminas\Http\Response;
use Psr\Log\LoggerInterface;

/**
 * Class GatewayCommand
 * @api
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GatewayCommand implements CommandInterface
{
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
     * GatewayCommand constructor.
     *
     * @param BuilderInterface $requestBuilder
     * @param TransferFactoryInterface $transferFactory
     * @param ClientInterface $client
     * @param LoggerInterface $logger
     * @param ExceptionFactory $exceptionFactory
     * @param DecoderInterface $jsonDecoder
     * @param ProfilerInterface $profiler
     * @param HandlerInterface|null $handler
     * @param ValidatorInterface|null $validator
     */
    public function __construct(
        BuilderInterface $requestBuilder,
        TransferFactoryInterface $transferFactory,
        ClientInterface $client,
        LoggerInterface $logger,
        ExceptionFactory $exceptionFactory,
        DecoderInterface $jsonDecoder,
        ProfilerInterface $profiler,
        ?HandlerInterface $handler = null,
        ?ValidatorInterface $validator = null
    ) {
        $this->requestBuilder = $requestBuilder;
        $this->transferFactory = $transferFactory;
        $this->client = $client;
        $this->handler = $handler;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->exceptionFactory = $exceptionFactory;
        $this->jsonDecoder = $jsonDecoder;
        $this->profiler = $profiler;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $commandSubject
     *
     * @return ResultInterface|array|null
     * @throws ClientException
     * @throws ConverterException
     * @throws LocalizedException
     * @throws VippsException
     */
    public function execute(array $commandSubject)
    {
        $transfer = $this->transferFactory->create(
            $this->requestBuilder->build($commandSubject)
        );

        $commandSubject['transferObject'] = $transfer;

        $result = $this->client->placeRequest($transfer);

        /** @var Response $response */
        $response = $result['response'];

        try {
            $responseBody = $this->jsonDecoder->decode($response->getContent());
        } catch (\Exception $e) {
            $responseBody = [];
        }

        $this->profiler->save($transfer, $response);

        if (!$response->isSuccess()) {
            $error = $this->extractError($responseBody);
            $orderId = $this->extractOrderId($transfer, $responseBody);
            $errorCode = $error['code'] ?? $response->getStatusCode();
            $errorMessage = $error['message'] ?? $response->getReasonPhrase();
            $exception = $this->exceptionFactory->create($errorCode, $errorMessage);
            $message = sprintf(
                'Request error. Code: "%s", message: "%s", order id: "%s"',
                $errorCode,
                $errorMessage,
                $orderId
            );
            $this->logger->critical($message);
            throw $exception;
        }

        /** Validating Success response body by specific command validators */
        if ($this->validator !== null) {
            $validationResult = $this->validator->validate(
                array_merge($commandSubject, ['jsonData' => $responseBody])
            );
            if (!$validationResult->isValid()) {
                $this->logValidationFails($validationResult->getFailsDescription());
                throw new CommandException(
                    __('Transaction validation failed.')
                );
            }
        }

        /** Handling response after validation is success */
        if ($this->handler) {
            $this->handler->handle($commandSubject, $responseBody);
        }

        return $responseBody;
    }

    /**
     * @param Phrase[] $fails
     *
     * @return void
     */
    private function logValidationFails(array $fails)
    {
        foreach ($fails as $failPhrase) {
            $this->logger->critical((string) $failPhrase);
        }
    }

    /**
     * Method to extract error code and message from response.
     *
     * @param $responseBody
     *
     * @return array
     */
    private function extractError($responseBody)
    {
        return [
            'code' => isset($responseBody[0]['errorCode']) ? $responseBody[0]['errorCode'] : null,
            'message' => isset($responseBody[0]['errorMessage']) ? $responseBody[0]['errorMessage'] : null,
        ];
    }

    /**
     * @param TransferInterface $transfer
     * @param array $responseBody
     *
     * @return string|null
     */
    private function extractOrderId($transfer, $responseBody)
    {
        $orderId = null;
        if (preg_match('/payments(\/([^\/]+)\/([a-z]+))?$/', $transfer->getUri(), $matches)) {
            $orderId = $matches[2] ?? null;
        }

        return $orderId ?? ($transfer->getBody()['transaction']['orderId'] ?? ($responseBody['orderId'] ?? null));
    }
}
