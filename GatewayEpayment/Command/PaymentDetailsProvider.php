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

namespace Vipps\Payment\GatewayEpayment\Command;

use Vipps\Payment\GatewayEpayment\Data\Payment;
use Vipps\Payment\GatewayEpayment\Data\PaymentBuilder;
use Vipps\Payment\GatewayEpayment\Data\Session;
use Vipps\Payment\Api\Payment\CommandManagerInterface;
use Vipps\Payment\Gateway\Exception\VippsException;
use Vipps\Payment\Gateway\Transaction\Transaction;
use Vipps\Payment\Gateway\Transaction\TransactionBuilder;
use Vipps\Payment\GatewayEpayment\Data\SessionBuilder;

/**
 * Class PaymentDetailsProvider
 * @package Vipps\Payment\Gateway\Command
 * @spi
 */
class PaymentDetailsProvider implements  \Vipps\Payment\Api\Transaction\PaymentDetailsInterface
{
    /**
     * @var CommandManagerInterface
     */
    private $commandManager;

    /**
     * @var PaymentBuilder
     */
    private $paymentBuilder;

    /**
     * @var array
     */
    private $cache = [];

    /**
     * PaymentDetailsProvider constructor.
     *
     * @param CommandManagerInterface $commandManager
     * @param SessionBuilder $paymentBuilder
     */
    public function __construct(
        CommandManagerInterface $commandManager,
        PaymentBuilder          $paymentBuilder
    ) {
        $this->commandManager = $commandManager;
        $this->paymentBuilder = $paymentBuilder;
    }

    /**
     * @param string $orderId
     *
     * @return Payment
     * @throws VippsException
     */
    public function get(string $orderId): ?Payment
    {
        if (!isset($this->cache[$orderId])) {
            /**
             * {
             *  "aggregate":{"authorizedAmount":{"currency":"NOK","value":40000},"cancelledAmount":{"currency":"NOK","value":0},"capturedAmount":{"currency":"NOK","value":0},"refundedAmount":{"currency":"NOK","value":0}},
             *  "amount":{"currency":"NOK","value":40000},
             *  "state":"AUTHORIZED",
             *  "paymentMethod":{"type":"WALLET","cardBin":"494656"},
             *  "profile":[],"pspReference":
             *  "fd750d5c-be6a-49ec-963a-fc076e1166ae",
             *  "redirectUrl":"https:\/\/apitest.vipps.no\/dwo-api-application\/v1\/deeplink\/vippsgateway?v=2&token=eyJraWQiOiJqd3RrZXkiLCJhbGciOiJSUzI1NiJ9.eyJhcHAiOiJlUGF5bWVudCIsInN1YiI6ImYwMTQyYjFjLTZiMjAtNDUzYy1hOWYxLWUxZTBkYWI2OTM5NyIsIm5vIjp7IkhlYWRlciI6IkJldGFsIiwiQ29udGVudCI6IjQwMCBrciB0aWwgVmFpbW8ifSwibWVyY2hhbnRTZXJpYWxOdW1iZXIiOiIyMTE3NTIiLCJpc3MiOiJodHRwczpcL1wvVklQUFMtTVQtQ09OLUFHRU5ULWlsYi50ZWNoLTAyLm5ldFwvbXQxXC9kZWVwbGluay1vcGVuaWQtcHJvdmlkZXItYXBwbGljYXRpb25cLyIsImVuIjp7IkhlYWRlciI6IlBheSIsIkNvbnRlbnQiOiI0MDAga3IgdG8gVmFpbW8ifSwiMWZhIjoidHJ1ZSIsInR5cGUiOiJQQVlNRU5UIiwidGl0bGUiOiI0MDAga3IiLCJ1dWlkIjoiZmQ3NTBkNWMtYmU2YS00OWVjLTk2M2EtZmMwNzZlMTE2NmFlIiwicmVmZXJlbmNlSWQiOiJudmQyNDUtMDAwMDAwMTgzIiwidXJsIjoiaHR0cHM6XC9cL2FwaXRlc3QudmlwcHMubm9cL3ZpcHBzLWVwYXltZW50LWxlZ2FjeS1tb2JpbGUtYXBpXC9sYW5kaW5nLXBhZ2UiLCJhdWQiOiJmMDE0MmIxYy02YjIwLTQ1M2MtYTlmMS1lMWUwZGFiNjkzOTciLCJhenAiOiJmMDE0MmIxYy02YjIwLTQ1M2MtYTlmMS1lMWUwZGFiNjkzOTciLCJhcHBUeXBlIjoiTEFORElOR1BBR0UiLCJub0VkaXQiOmZhbHNlLCJzY29wZXMiOltdLCJleHAiOjE2OTc3MjgxNDEsInRva2VuVHlwZSI6IkRFRVBMSU5LIiwiaWF0IjoxNjk3NzI3NTQxLCJmYWxsYmFjayI6Imh0dHBzOlwvXC83NjQxLTc5LTExMC0xMzQtMTQ1Lm5ncm9rLWZyZWUuYXBwXC92aXBwc1wvcGF5bWVudFwvZmFsbGJhY2tcL3JlZmVyZW5jZVwvbnZkMjQ1LTAwMDAwMDE4M1wvIiwianRpIjoiZjA4NTg1NzQtNzIxOC00ZjY3LWExNGYtNWQxMjE3M2Q1ODY4In0.eBZFcEB_j9huNKlQnxTgSlfRCTHl1Pf3tC6x-pIqVG6CA-7ZpPyy960UvfZnC2OjhxDJ_UMdsDDjmnjBjxAbwiOf-G5q8H_E7ZIMU3h1zLJPEDzFFHZe920SDxc41eA7CiERhnIeEkUcHhsmokQdmNBjd2ODHKyC7z0MLRoE8pkfYcjig0OSYrRgpgIIzvjTmZcxy-PjSZOPv4UMCf1aPjGtz1C0TRFm5AgfuW-3fGqrhBskzfZVC6rKaA4Veg0Cquljh-PkHrj_QLriHNnhfAd0_t2xvDuebuwoCaUPzKYKCqVGkyQHpvL6rjwKxcK7BQf0QSaL-vuD7ZDuLhiFWw",
             *  "reference":"nvd245-000000183"
             * }
             */
            $response = $this->commandManager->getPayment($orderId);
            $transaction = $this->paymentBuilder->setData($response)->build();

            $this->cache[$orderId] = $transaction;
        }

        return $this->cache[$orderId];
    }
}
