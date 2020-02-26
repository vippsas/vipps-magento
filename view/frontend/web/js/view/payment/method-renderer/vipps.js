/**
 * Copyright 2018 Vipps
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
/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'mage/storage',
        'mage/url',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function (
        $,
        storage,
        url,
        Component,
        errorProcessor,
        fullScreenLoader
    ) {
        'use strict';

        return Component.extend({
            redirectUrl: null,

            defaults: {
                redirectAfterPlaceOrder: false,
                template: 'Vipps_Payment/payment/vipps'
            },

            /** Returns payment image path */
            getVippsLogoSrc: function () {
                return window.checkoutConfig.payment.vipps.logoSrc;
            },

            afterPlaceOrder: function () {
                $.mage.redirect(this.redirectUrl);
            },

            continueToVipps: function (data, event) {
                let self = this;

                self.isPlaceOrderActionAllowed(false);
                fullScreenLoader.startLoader();

                $.post(
                    url.build('vipps/payment/initRegular'), {}
                ).done(
                    function(response, msg, xhr) {
                        if (response.hasOwnProperty('url')) {
                            self.redirectUrl = response.url;

                            self.isPlaceOrderActionAllowed(true);
                            return self.placeOrder(data, event);
                        } else {
                            errorProcessor.process(xhr, self.messageContainer);
                        }
                    }
                ).fail(
                    function (response) {
                        errorProcessor.process(response, self.messageContainer);
                    }
                ).always(
                    function() {
                        fullScreenLoader.stopLoader();
                        self.isPlaceOrderActionAllowed(true);
                    }
                );

                return false;
            }
        });
    }
);
