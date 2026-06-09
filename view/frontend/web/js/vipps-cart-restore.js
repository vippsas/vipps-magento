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
define(['jquery', 'Magento_Customer/js/customer-data'], function ($, customerData) {
    'use strict';

    return function (config) {
        var PENDING_COOKIE = 'vipps_pending_quote_id';
        var RESTORED_COOKIE = 'vipps_cart_restored';

        function getCookie(name) {
            var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
            return match ? decodeURIComponent(match[1]) : null;
        }

        function ajaxRestore() {
            $.ajax({
                url: config.restoreUrl,
                type: 'POST',
                dataType: 'json',
                success: function (response) {
                    if (response.restored) {
                        customerData.reload(['cart'], false);
                    }
                }
            });
        }

        if (window.location.href.indexOf(config.successUrl) !== -1) {
            customerData.reload(['cart'], false);
            return;
        }

        window.addEventListener('pageshow', function (event) {
            if (event.persisted && getCookie(PENDING_COOKIE)) {
                ajaxRestore();
            }
        });

        // Observer ran server-side on the cart page — reload sections to sync JS state
        if (getCookie(RESTORED_COOKIE)) {
            document.cookie = RESTORED_COOKIE + '=; path=/; max-age=0';
            customerData.reload(['cart', 'messages'], false);
            return;
        }

        // Fresh load on any non-cart page: observer didn't run, AJAX restore
        if (getCookie(PENDING_COOKIE)) {
            ajaxRestore();
        }
    };
});
