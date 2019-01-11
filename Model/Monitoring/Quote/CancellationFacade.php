<?php
/**
 * Copyright 2018 Vipps
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
 *  documentation files (the "Software"), to deal in the Software without restriction, including without limitation
 *  the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software,
 *  and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED
 *  TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL
 *  THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
 *  CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 *  IN THE SOFTWARE.
 *
 */

namespace Vipps\Payment\Model\Monitoring\Quote;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartInterface;
use Vipps\Payment\{
    Api\CommandManagerInterface,
    Api\Monitoring\Data\QuoteInterface,
    Api\Monitoring\Quote\CancellationFacadeInterface,
    Gateway\Transaction\Transaction,
    Model\Monitoring\QuoteRepository,
    Model\Order\Cancellation\Config,
    Model\ResourceModel\Monitoring\Quote\Cancellation\Type as CancellationTypeResource};

/**
 * Quote Cancellation Facade.
 * It cancels the quote. Provides an ability to send cancellation request to Vipps.
 */
class CancellationFacade implements CancellationFacadeInterface
{
    /**
     * @var CommandManagerInterface
     */
    private $commandManager;
    /**
     * @var CancellationFactory
     */
    private $cancellationFactory;
    /**
     * @var CancellationRepository
     */
    private $cancellationRepository;
    /**
     * @var Config
     */
    private $config;
    /**
     * @var QuoteRepository
     */
    private $quoteRepository;
    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    private $adapter;
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * CancellationFacade constructor.
     * @param CommandManagerInterface $commandManager
     * @param CancellationFactory $cancellationFactory
     * @param CancellationRepository $cancelRepository
     * @param QuoteRepository $quoteRepository
     * @param ResourceConnection $resourceConnection
     * @param Config $config
     */
    public function __construct(
        CommandManagerInterface $commandManager,
        CancellationFactory $cancellationFactory,
        CancellationRepository $cancelRepository,
        QuoteRepository $quoteRepository,
        ResourceConnection $resourceConnection,
        Config $config
    ) {
        $this->commandManager = $commandManager;
        $this->cancellationFactory = $cancellationFactory;
        $this->cancellationRepository = $cancelRepository;
        $this->config = $config;
        $this->quoteRepository = $quoteRepository;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * vipps_monitoring extension attribute requires to be loaded in the quote.
     *
     * @param CartInterface $quote
     * @param string $type
     * @param string $reason
     * @param Transaction|null $transaction
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Exception
     */
    public function cancelMagento(
        CartInterface $quote,
        string $type,
        string $reason,
        Transaction $transaction = null
    ) {
        /** @var \Vipps\Payment\Api\Monitoring\Data\QuoteInterface $quoteMonitoring */
        $quoteMonitoring = $quote->getExtensionAttributes()->getVippsMonitoring();

        if (!$quoteMonitoring) {
            throw new NoSuchEntityException(__('Vipps Monitoring model is not loaded in Quote extension attributes'));
        }

        $connection = $this->resourceConnection->getConnection();

        // Creating quote cancellation.
        $cancellation = $this
            ->cancellationFactory
            ->create($quoteMonitoring, $type, $reason);

        $quoteMonitoring->setIsCanceled(QuoteInterface::IS_CANCELED_YES);

        $connection->beginTransaction();
        try {
            $this->quoteRepository->save($quoteMonitoring);
            $this->cancellationRepository->save($cancellation);
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }

        // Nothing to cancel if cancellation initialed by Vipps.
        if ($type === CancellationTypeResource::MAGENTO
            && $this->isAutomaticVippsCancellation($quote->getStoreId())
        ) {
            $this->cancelVipps($quote, $cancellation, $transaction);
        }
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    private function isAutomaticVippsCancellation($storeId = null)
    {
        return $this->config->isTypeAutomatic($storeId);
    }

    /**
     * @param CartInterface $quote
     * @param Cancellation $cancellation
     * @param Transaction|null $transaction
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function cancelVipps(
        CartInterface $quote,
        Cancellation $cancellation,
        Transaction $transaction = null
    ) {
        // cancel order on vipps side
        if ($transaction && $transaction->isTransactionReserved()) {
            $this->commandManager->cancel($quote->getPayment());

            $cancellation->setType(CancellationTypeResource::ALL);
            $this->cancellationRepository->save($cancellation);
        }
    }
}
