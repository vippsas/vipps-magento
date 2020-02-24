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
define(
    [
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_CheckoutAgreements/js/model/agreements-assigner'
    ],
    function ($, quote, urlBuilder, storage, errorProcessor, customer, fullScreenLoader, agreementsAssigner) {
        'use strict';


        /**
         * Filter template data.
         *
         * @param {Object|Array} data
         */
        var filterTemplateData = function (data) {
            return _.each(data, function (value, key, list) {
                if (_.isArray(value) || _.isObject(value)) {
                    list[key] = filterTemplateData(value);
                }

                if (key === '__disableTmpl') {
                    delete list[key];
                }
            });
        };

        return function (messageContainer) {
            var serviceUrl,
                payload,
                method = 'put',
                paymentData = quote.paymentMethod();

            // starting from the M 2.3.3 some keys have to be removed from the payload before sending
            // please see module-checkout/view/frontend/web/js/action/set-payment-information-extended.js
            // but as it's not fully compatible with our logic, we have to add handle for this separately
            paymentData = filterTemplateData(paymentData);

            /**
             * Checkout for guest and registered customer.
             */
            if (!customer.isLoggedIn()) {
                serviceUrl = urlBuilder.createUrl('/guest-carts/:cartId/set-payment-information', {
                    cartId: quote.getQuoteId()
                });
                payload = {
                    cartId: quote.getQuoteId(),
                    email: quote.guestEmail,
                    paymentMethod: paymentData
                };
                method = 'post';
            } else {
                serviceUrl = urlBuilder.createUrl('/carts/mine/selected-payment-method', {});
                payload = {
                    cartId: quote.getQuoteId(),
                    method: paymentData
                };
            }
            agreementsAssigner(paymentData);
            fullScreenLoader.startLoader();

            return storage[method](
                serviceUrl, JSON.stringify(payload)
            ).fail(
                function (response) {
                    errorProcessor.process(response, messageContainer);
                    fullScreenLoader.stopLoader();
                }
            );
        };
    }
);
