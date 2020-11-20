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

return \json_decode(
    '{
        "orderId": "testOrderId",
        "transactionLogHistory": [
            {
                "amount": 20000,
                "transactionText": "One pair of Vipps socks",
                "transactionId": "5001420062",
                "timeStamp": "2018-11-14T15:21:22.126Z",
                "operation": "RESERVE",
                "requestId": "",
                "operationSuccess": false
            },
            {
                "amount": 20000,
                "transactionText": "One pair of Vipps socks",
                "transactionId": "5001420062",
                "timeStamp": "2018-11-14T15:21:04.697Z",
                "operation": "INITIATE",
                "requestId": "",
                "operationSuccess": true
            }
        ]
    }',
    true
);
