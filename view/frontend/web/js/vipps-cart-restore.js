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
define(['jquery', 'Magento_Customer/js/customer-data', 'mage/cookies'], function ($, customerData) {
    'use strict';

    return function (config) {
        var PENDING_COOKIE = 'vipps_pending_quote_id';
        var RESTORED_COOKIE = 'vipps_cart_restored';

        // Magento's full-page loader while the payment is checked; left up when redirecting to the confirmation.
        function showLoader() {
            $('body').trigger('processStart');
        }

        function hideLoader() {
            $('body').trigger('processStop');
        }

        function getCookie(name) {
            var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
            return match ? decodeURIComponent(match[1]) : null;
        }

        /**
         * Asks whether the restored cart's payment was already approved; runs after render, never blocking it.
         *
         * @param {Array} sections refreshed unless redirecting, so an approved cart never flashes as restored
         */
        function checkReturn(sections) {
            $.ajax({
                url: config.checkReturnUrl,
                type: 'POST',
                dataType: 'json',
                data: {form_key: $.mage.cookies.get('form_key')},
                success: function (response) {
                    if (response.orderPlaced && response.redirectUrl) {
                        window.location.assign(response.redirectUrl);
                        return;
                    }
                    if (sections.length || response.pending) {
                        customerData.reload(sections.concat(['messages']), false);
                    }
                    hideLoader();
                },
                error: function () {
                    if (sections.length) {
                        customerData.reload(sections.concat(['messages']), false);
                    }
                    hideLoader();
                }
            });
        }

        function ajaxRestore() {
            showLoader();
            $.ajax({
                url: config.restoreUrl,
                type: 'POST',
                dataType: 'json',
                success: function (response) {
                    if (response.restored) {
                        // The cart and the restore notice are refreshed once checkReturn has answered.
                        checkReturn(['cart']);
                    } else {
                        hideLoader();
                    }
                },
                error: hideLoader
            });
        }

        if (window.location.href.indexOf(config.successUrl) !== -1) {
            customerData.reload(['cart'], false);
            return;
        }

        window.addEventListener('pageshow', function (event) {
            if (!event.persisted) {
                return;
            }
            // Back from the confirmation can restore this page with the loader still up.
            hideLoader();
            if (getCookie(PENDING_COOKIE)) {
                ajaxRestore();
            }
        });

        // Observer ran server-side on the cart page — reload sections to sync JS state
        if (getCookie(RESTORED_COOKIE)) {
            document.cookie = RESTORED_COOKIE + '=; path=/; max-age=0';
            customerData.reload(['cart', 'messages'], false);
            showLoader();
            checkReturn([]);
            return;
        }

        // Fresh load on any non-cart page: observer didn't run, AJAX restore
        if (getCookie(PENDING_COOKIE)) {
            ajaxRestore();
        }
    };
});
