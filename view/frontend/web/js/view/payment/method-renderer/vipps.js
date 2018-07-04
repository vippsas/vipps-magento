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
        'Magento_Checkout/js/view/payment/default',
        'Vipps_Payment/js/action/set-payment-method',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function (
        $,
        Component,
        setPaymentMethodAction,
        additionalValidators,
        customerData,
        fullScreenLoader
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Vipps_Payment/payment/vipps'
            },

            /** Returns payment image path */
            getVippsLogoSrc: function () {
                return window.checkoutConfig.payment.vipps.logoSrc;
            },

            /** Returns payment continue button image path */
            getContinueVippsImgSrc: function () {
                return window.checkoutConfig.payment.vipps.continueImgSrc;
            },

            /** Redirect to vipps */
            continueToVipps: function () {
                event.preventDefault();
                if (additionalValidators.validate()) {
                    //update payment method information if additional data was changed
                    this.selectPaymentMethod();
                    var self = this;
                    setPaymentMethodAction(this.messageContainer).done(
                        function () {
                            $.post(
                                window.checkoutConfig.payment.vipps.initiateUrl
                            ).done(
                                function (response) {
                                    if (response.hasOwnProperty('url')) {
                                        $.mage.redirect(response.url);
                                    } else if (response.hasOwnProperty('errorMessage')) {
                                        self.messageContainer.addErrorMessage({
                                            "message": response.errorMessage
                                        });
                                    } else {
                                        customerData.invalidate(['cart']);
                                        window.location.reload();
                                    }
                                }
                            ).fail(
                                function () {
                                    window.location.reload();
                                }
                            ).always(
                                function () {
                                    customerData.invalidate(['cart']);
                                    fullScreenLoader.stopLoader();
                                }
                            );

                        }.bind(this)
                    )
                }
                return true;
            }
        });
    }
);
