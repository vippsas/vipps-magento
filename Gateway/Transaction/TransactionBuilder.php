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
namespace Vipps\Payment\Gateway\Transaction;

use Vipps\Payment\Gateway\Transaction\TransactionLogHistory\ItemFactory;

/**
 * Class TransactionBuilder
 * @package Vipps\Payment\Gateway\Transaction
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class TransactionBuilder
{
    /**
     * @var TransactionFactory
     */
    private $transactionFactory;

    /**
     * @var TransactionInfoFactory
     */
    private $infoFactory;

    /**
     * @var TransactionSummaryFactory
     */
    private $summaryFactory;

    /**
     * @var TransactionLogHistoryFactory
     */
    private $logHistoryFactory;

    /**
     * @var ItemFactory
     */
    private $itemFactory;

    /**
     * @var ShippingDetailsFactory
     */
    private $shippingDetailsFactory;

    /**
     * @var UserDetailsFactory
     */
    private $userDetailsFactory;

    /**
     * @var array
     */
    private $response;

    /**
     * TransactionBuilder constructor.
     *
     * @param TransactionFactory $transactionFactory
     * @param TransactionInfoFactory $infoFactory
     * @param TransactionSummaryFactory $summaryFactory
     * @param TransactionLogHistoryFactory $logHistoryFactory
     * @param ItemFactory $itemFactory
     * @param UserDetailsFactory $userDetailsFactory
     * @param ShippingDetailsFactory $shippingDetailsFactory
     */
    public function __construct(
        TransactionFactory $transactionFactory,
        TransactionInfoFactory $infoFactory,
        TransactionSummaryFactory $summaryFactory,
        TransactionLogHistoryFactory $logHistoryFactory,
        ItemFactory $itemFactory,
        UserDetailsFactory $userDetailsFactory,
        ShippingDetailsFactory $shippingDetailsFactory
    ) {
        $this->transactionFactory = $transactionFactory;
        $this->infoFactory = $infoFactory;
        $this->summaryFactory = $summaryFactory;
        $this->logHistoryFactory = $logHistoryFactory;
        $this->itemFactory = $itemFactory;
        $this->userDetailsFactory = $userDetailsFactory;
        $this->shippingDetailsFactory = $shippingDetailsFactory;
    }

    /**
     * Set request to builder
     *
     * @param $response
     *
     * @return $this
     */
    public function setData($response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * build transaction object
     *
     * @return Transaction
     */
    public function build()
    {
        $orderId = $this->response['orderId'];
        $infoData = $this->response['transactionInfo'] ?? $this->response['transaction'] ?? [];
        $info = $this->infoFactory->create(['data' => $infoData]);

        $summaryData = $this->response['transactionSummary'] ?? [];
        $summary = $this->summaryFactory->create(['data' => $summaryData]);

        $logHistoryData = $this->response['transactionLogHistory'] ?? [];
        $items = [];
        foreach ($logHistoryData as $itemData) {
            $items[] = $this->itemFactory->create(['data' => $itemData]);
        }
        $logHistory = $this->logHistoryFactory->create(['data' => ['items' => $items]]);

        $arguments = [
            'orderId' => $orderId,
            'transactionInfo' => $info,
            'transactionSummary' => $summary,
            'transactionLogHistory' => $logHistory
        ];

        if (isset($this->response['userDetails'])) {
            $arguments['userDetails'] = $this->userDetailsFactory->create(['data' => $this->response['userDetails']]);
        }

        if (isset($this->response['shippingDetails'])) {
            $arguments['shippingDetails'] = $this->shippingDetailsFactory->create([
                'data' => $this->response['shippingDetails']
            ]);
        }

        return $this->transactionFactory->create($arguments);
    }
}
