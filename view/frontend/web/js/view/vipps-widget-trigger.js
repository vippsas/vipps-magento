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
define([
    'jquery',
    'jquery/ui'
], function ($) {
    'use strict';

    $.widget('mage.vippsWidgetTrigger', {
        options: {
            triggerUrl: '',
            isCatalogProduct: false,
            language: ''
        },

        /** @private */
        _create: function () {
            if (typeof window.vipps === 'undefined') {
                return;
            }

            if (!window.vippsWidgetHostStarted) {
                window.vipps.host().start();
                window.vippsWidgetHostStarted = true;
            }

            this._trigger = window.vipps.trigger($.proxy(this._createSession, this));

            this._on(this.element, {
                click: this._handleClick
            });
        },

        /** @private */
        _handleClick: function (event) {
            event.preventDefault();
            this._trigger.open(this.options.language ? {language: this.options.language} : undefined);
        },

        /**
         * Adds the viewed product to cart (on PDP) then creates a Vipps payment session.
         *
         * @return {Promise<string>}
         * @private
         */
        _createSession: function () {
            var addToCart = this.options.isCatalogProduct
                ? this._addToCart()
                : $.Deferred().resolve().promise();

            return addToCart.then($.proxy(function () {
                return $.ajax({
                    url: this.options.triggerUrl,
                    type: 'POST',
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(function (response) {
                    // An earlier payment for this cart was already approved: go to the order, or to the
                    // cart where the shopper is told it is being completed, instead of paying again.
                    if (response && response.redirectUrl) {
                        window.location.assign(response.redirectUrl);

                        return $.Deferred().promise();
                    }

                    if (!response || !response.paymentUrl) {
                        throw new Error(response && response.message || 'Vipps Widget: unable to start payment.');
                    }

                    return response.paymentUrl;
                });
            }, this));
        },

        /**
         * Submits the PDP add-to-cart form via AJAX before the payment session is created.
         *
         * @return {jQuery.promise}
         * @private
         */
        _addToCart: function () {
            var $form = $('#product_addtocart_form');

            if (!$form.length) {
                return $.Deferred().reject(new Error('Vipps Widget: product_addtocart_form not found')).promise();
            }

            return $.ajax({
                url: $form.attr('action'),
                type: 'POST',
                data: $form.serialize(),
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function (response) {
                if (response && response.product && response.product.statusText) {
                    throw new Error(response.product.statusText);
                }

                return response;
            });
        }
    });

    return $.mage.vippsWidgetTrigger;
});
