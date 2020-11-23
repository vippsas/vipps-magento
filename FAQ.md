# Vipps for Magento: Frequently Asked questions

### How can I get help with Vipps for Magento?

Vipps for Magento is developed by [Vaimo](https://www.vaimo.com), and the same developers who made
the plugin also help with improvements, maintenance and developer assistance.

If you are having a problem, please make sure that you are using the latest version:
https://github.com/vippsas/vipps-magento/releases

The best way to report a problem (or ask a question) is to use GitHub's built-in "issue" functionality:
[Issues](https://github.com/vippsas/vipps-magento/issues)

### How can I get help with Vipps (unrelated to Magento)?

See: [How to contact Vipps Integration](https://github.com/vippsas/vipps-developers/blob/master/contact.md).

## Why does it take so long after purchase before orders are created in Magento?

Vipps depends on a proper cron job configuration for background processing of orders. 
Failure to set it up properly means Vipps will not function as expected.

## Why are some orders missing in Magento?

This scenario is possible for express payment flow. Unlike of regular payment, order is not created before redirecting to
Vipps Landing page. If transaction was successfully initiated by client  - inside DB table `vipps_quote` will be created a new record
with a quote ID and reserved order ID. This can be helpful to find a status of a transaction.

## Klarna Checkout

There is a known issue when using Vipps as an external payment method for Klarna Checkout. 
When customer fills in checkout form using data that were used before, customer will see an error:

`Please check the shipping address information. "firstname" is required. Enter and try again. "lastname" is required. Enter and try again. "street" is required. Enter and try again. "city" is required. Enter and try again. "telephone" is required. Enter and try again. "postcode" is required. Enter and try again.`

This issue will be fixed in next releases.


## Quote Monitoring Tool

From 1.2.1 version we released Quote Monitoring.

It simplifies detection of failed order placement and identifies the root causes in cases of failure.

The monitoring tool is located under **System -> Vipps Payment -> Quote Monitoring**.
This page displays all orders that were attempted to be placed.
Each record in the list provides detailed information about order creation flow: current status, list of attempts, each attempt results.

Monitoring quote statuses explanation:

*New* - payment is initiated on the Vipps side.

*Processing* - Magento has started processing for initiated payment.

*Placed* - The order has been placed.

*Expired* - The customer has not approved payment for some time.

*Placement Failed* - All attempts were unsuccessful.

*Canceled* - The payment has been canceled (Cancellation can be initiated by the customer in Vipps or manually/automatically by Magento for Quotes in Placement Failed status)

*Cancel Failed* - Means failed to cancel payment. Record in this status require admin/developer interaction.

## Tax Calculation for Express Checkout
When enabling the Express checkout payment in the configuration area you may see a notification at the top of admin panel saying:
![Express Checkout notice](docs/images/express-checkout-notice.png)

This means that you should change Tax Calculation Settings to be based on **Shipping Origin**:
![Tax Calculation Settings](docs/images/tax-origin-settings.png)

Otherwise an issue with calculating delivery cost might occur.
