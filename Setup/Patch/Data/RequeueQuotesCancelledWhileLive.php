<?php
/*
 * Copyright 2026 Vipps
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
declare(strict_types=1);

namespace Vipps\Payment\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;
use Vipps\Payment\Api\Data\QuoteStatusInterface;

/**
 * Re-queues monitoring quotes that 3.0.2 and 3.0.3 cancelled while the payment was still live.
 *
 * Those versions cancelled a monitoring quote whenever the shopper's browser returned to the
 * store, without reading the payment state. Because FetchOrderFromVipps only looks at new and
 * pending quotes, a payment the shopper went on to complete in the Vipps/MobilePay app was left
 * authorised with no route into Magento and nothing that would ever look at it again.
 *
 * Putting those rows back to pending hands them to the existing cron, which asks Vipps what
 * actually happened and places, cancels or expires each one on the evidence. This patch makes no
 * judgement about any payment itself and calls no API.
 *
 * One-time repair of data written by two specific versions, not an ongoing reconciliation: from
 * 3.0.4 on, every writer of the cancelled status has either read the payment state from Vipps or
 * cancelled the payment there itself, so the status can be trusted again.
 */
class RequeueQuotesCancelledWhileLive implements DataPatchInterface
{
    /**
     * Rows older than this are past the point where the authorisation behind them could still be
     * captured, so re-checking them would cost an API call each to learn they have expired.
     */
    private const WINDOW_DAYS = 7;

    /**
     * Quotes at or above this were cancelled by CancelQuoteByAttempts, which calls the cancel
     * operation at Vipps/MobilePay before writing the status, so their payments really are dead.
     * The floor is fixed rather than configurable because that cron takes max(3, configured).
     */
    private const ATTEMPTS_AUTOMATIC_CANCEL_FLOOR = 3;

    private const BATCH_SIZE = 1000;

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly LoggerInterface $logger
    ) {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('vipps_quote');

        $select = $connection->select()
            ->from(['main_table' => $table], ['entity_id'])
            ->where('main_table.status = ?', QuoteStatusInterface::STATUS_CANCELED)
            ->where('main_table.order_id IS NULL')
            ->where('main_table.quote_id IS NOT NULL')
            // Anything the cart restore could have cancelled is still in range: a cron tick landing
            // mid-payment raises attempts without resolving the payment, so these are not all zero.
            ->where('main_table.attempts < ?', self::ATTEMPTS_AUTOMATIC_CANCEL_FLOOR)
            ->where(
                sprintf(
                    'main_table.updated_at >= UTC_TIMESTAMP() - INTERVAL %d DAY',
                    self::WINDOW_DAYS
                )
            )
            // A cart that produced an order through another payment attempt is already settled.
            // Re-queueing its other attempt risks a second order for the one cart.
            ->where(
                'main_table.quote_id NOT IN (?)',
                new \Zend_Db_Expr(
                    (string)$connection->select()
                        ->from(['settled' => $table], ['quote_id'])
                        ->where('settled.order_id IS NOT NULL')
                        ->where('settled.quote_id IS NOT NULL')
                )
            );

        $entityIds = $connection->fetchCol($select);

        if (!$entityIds) {
            return $this;
        }

        foreach (array_chunk($entityIds, self::BATCH_SIZE) as $batch) {
            $connection->update(
                $table,
                ['status' => QuoteStatusInterface::STATUS_PENDING, 'attempts' => 0],
                ['entity_id IN (?)' => $batch]
            );
        }

        $this->logger->info(
            sprintf(
                'Vipps: re-queued %d monitoring quote(s) cancelled while the payment was still live.'
                . ' The order fetch cron will resolve each against Vipps.',
                count($entityIds)
            )
        );

        return $this;
    }

    /**
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}
