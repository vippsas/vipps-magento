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
namespace Vipps\Payment\Test\Unit\Gateway\Command;

use Vipps\Payment\Gateway\Command\GatewayCommand;
use Vipps\Payment\Gateway\Exception\ExceptionFactory;
use Vipps\Payment\Gateway\Exception\MerchantException;
use Vipps\Payment\Model\Profiling\ProfilerInterface;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Gateway\Validator\ValidatorInterface;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Validator\Result;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Json\DecoderInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Psr\Log\LoggerInterface;
use Laminas\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * Class GatewayCommandTest
 * @package Vipps\Payment\Test\Unit\Gateway\Command
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GatewayCommandTest extends TestCase
{
    /**
     * @var GatewayCommand
     */
    private $action;

    /**
     * @var BuilderInterface|MockObject
     */
    private $requestBuilder;

    /**
     * @var TransferFactoryInterface|MockObject
     */
    private $transferFactory;

    /**
     * @var ClientInterface|MockObject
     */
    private $client;

    /**
     * @var HandlerInterface|MockObject
     */
    private $handler;

    /**
     * @var ValidatorInterface|MockObject
     */
    private $validator;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var ExceptionFactory|MockObject
     */
    private $exceptionFactory;

    /**
     * @var DecoderInterface|MockObject
     */
    private $jsonDecoder;

    /**
     * @var ProfilerInterface|MockObject
     */
    private $profiler;

    /**
     * @var ObjectManager
     */
    private $objectManagerHelper;

    public function setUp()
    {
        $this->transferFactory = $this->getMockBuilder(TransferFactoryInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMockForAbstractClass();
        $this->profiler = $this->getMockBuilder(ProfilerInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['save'])
            ->getMockForAbstractClass();
        $this->requestBuilder = $this->getMockBuilder(BuilderInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['build'])
            ->getMockForAbstractClass();
        $this->client = $this->getMockBuilder(ClientInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['placeRequest'])
            ->getMockForAbstractClass();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['critical'])
            ->getMockForAbstractClass();
        $this->validator = $this->getMockBuilder(ValidatorInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['validate'])
            ->getMockForAbstractClass();
        $this->handler = $this->getMockBuilder(HandlerInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['handle'])
            ->getMockForAbstractClass();
        $this->objectManagerHelper = new ObjectManager($this);
        $this->jsonDecoder = $this->objectManagerHelper->getObject(\Magento\Framework\Json\Decoder::class, []);
        $localizedExceptionFactory = $this
            ->getMockBuilder(\Magento\Framework\Exception\LocalizedExceptionFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();
        $localizedException = $this->objectManagerHelper->getObject(
            LocalizedException::class,
            [
                'phrase' => __('Some error phrase here')
            ]
        );
        $localizedExceptionFactory->expects($this->any())
            ->method('create')
            ->willReturn($localizedException);
        $this->exceptionFactory = $this->objectManagerHelper->getObject(
            ExceptionFactory::class,
            [
                'localizedExceptionFactory' => $localizedExceptionFactory
            ]
        );
        $this->action = $this->objectManagerHelper->getObject(GatewayCommand::class, [
            'requestBuilder' => $this->requestBuilder,
            'transferFactory' => $this->transferFactory,
            'client' => $this->client,
            'logger' => $this->logger,
            'exceptionFactory' => $this->exceptionFactory,
            'jsonDecoder' => $this->jsonDecoder,
            'profiler' => $this->profiler,
            'handler' => $this->handler,
            'validator' => $this->validator,
        ]);
    }

    /**
     * @dataProvider dataProvider
     *
     * @param $result
     * @param $isExceptionExpected
     * @param $exception
     * @param null $validationResult
     *
     * @throws ClientException
     * @throws ConverterException
     * @throws LocalizedException
     * @throws \Vipps\Payment\Gateway\Exception\VippsException
     */
    public function testExecute($result, $isExceptionExpected, $exception, $validationResult = null)
    {
        $transfer = $this->getMockBuilder(TransferInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->requestBuilder->expects($this->once())
            ->method('build')
            ->willReturn([]);
        $this->transferFactory->expects($this->once())
            ->method('create')
            ->with([])
            ->willReturn($transfer);
        $this->client->expects($this->once())
            ->method('placeRequest')
            ->willReturn($result);
        $this->profiler->expects($this->any())
            ->method('save');
        if ($isExceptionExpected) {
            $this->expectException($exception);
        }
        if ($validationResult) {
            $this->validator->expects($this->once())
                ->method('validate')
                ->willReturn($validationResult);
        }
        $this->action->execute([]);
    }

    public function dataProvider()
    {
        $responseStrFail = "HTTP/1.1 402 Payment Required\r\nContent-Type: application/json;charset=UTF-8\r\n";
        $responseStrFail .= "X-Application-Context: dwo-payment:mt1:8443 Date: Tue, 15 May 2018 08:37:57 GMT\r\n\r\n";
        $responseStrFail .= '[{"errorCode":"35","errorMessage":"Requested Order not found"}]';
        $responseStrOk = "HTTP/1.1 200 OK\r\nContent-Type: application/json;charset=UTF-8\r\n";
        $responseStrOk .= "X-Application-Context: dwo-payment:mt1:8443 Date: Tue, 15 May 2018 08:48:02 GMT\r\n\r\n";
        $responseStrOk .= '{"orderId":"000000013","url":"https://apitest.vipps.no"}';

        $this->objectManagerHelper = new ObjectManager($this);
        $validationResultFailed = $this->objectManagerHelper->getObject(Result::class, [
            'isValid' => false,
            'failsDescription' => ['error1', 'error2']
        ]);
        $resultFailed = [
            'response' => Response::fromString($responseStrFail)
        ];
        $resultSuccess = [
            'response' => Response::fromString($responseStrOk)
        ];
        $validationResultOk = $this->objectManagerHelper->getObject(
            Result::class,
            [
                'isValid' => true,
                'failsDescription' => []
            ]
        );
        return [
            [$resultFailed, true, MerchantException::class],
            [$resultSuccess, false, null, $validationResultOk],
            [$resultSuccess, true, CommandException::class, $validationResultFailed],
        ];
    }
}
