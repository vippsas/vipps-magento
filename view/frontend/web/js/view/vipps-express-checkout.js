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
define([
           'jquery',
           'jquery/ui',
           'mage/mage'
       ], function ($) {
    'use strict';

    $.widget('mage.vippsCheckout', {
        options: {
            originalForm:
                'form:not(#product_addtocart_form_from_popup):has(input[name="product"][value=%1])',
            productId: 'input[type="hidden"][name="product"]',
            vippsCheckoutSelector: '[data-role=vipps-checkout-url]',
            vippsCheckoutInput: '<input type="hidden" data-role="vipps-checkout-url" name="return_url" value=""/>'
        },

        /**
         * Initialize store credit events
         * @private
         */
        _create: function () {
            this.element.on('click', '[data-action="checkout-form-submit"]', $.proxy(function (e) {
                var $target = $(e.target),
                    returnUrl = $target.data('redirect-url'),
                    productId = $target.closest('form').find(this.options.productId).val(),
                    originalForm = this.options.originalForm.replace('%1', productId);

                e.preventDefault();

                this._redirect(returnUrl, originalForm);
            }, this));
        },

        /**
         * Redirect to certain url, with optional form
         * @param {String} returnUrl
         * @param {HTMLElement} originalForm
         *
         */
        _redirect: function (returnUrl, originalForm) {
            var $form,
                vippsCheckoutInput;

            if (this.options.isCatalogProduct) {
                // find the form from which the button was clicked
                $form = originalForm ? $(originalForm) : $($(this.options.shortcutContainerClass).closest('form'));

                vippsCheckoutInput = $form.find(this.options.vippsCheckoutSelector)[0];

                if (!vippsCheckoutInput) {
                    vippsCheckoutInput = $(this.options.vippsCheckoutInput);
                    vippsCheckoutInput.appendTo($form);
                }
                $(vippsCheckoutInput).val(returnUrl);

                $form.submit();
            } else {
                $.mage.redirect(returnUrl);
            }
        }
    });

    return $.mage.vippsCheckout;
});
