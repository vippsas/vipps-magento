
## Table of contents

- [How can I get help with Vipps for Magento?](#how-can-i-get-help-with-vipps-for-magento)
  - [How can I get help with Vipps (unrelated to Magento)?](#how-can-i-get-help-with-vipps-unrelated-to-magento)
- [Why does it take so long after purchase before orders are created in Magento?](#why-does-it-take-so-long-after-purchase-before-orders-are-created-in-magento)
- [Why are some orders missing in Magento?](#why-are-some-orders-missing-in-magento)
- [Klarna Checkout](#klarna-checkout)
- [Known issues](#known-issues)
- [How to enable debug mode / requests profiling](#how-to-enable-debug-mode-requests-profiling)
- [Quote Monitoring Tool](#quote-monitoring-tool)
- [Tax Calculation for Express Checkout](#tax-calculation-for-express-checkout)

## How can I get help with Vipps for Magento?

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

To enable Vipps Payment method for Klarna Checkout it should be chosen in the list of external payment method in appropriate Klarna Checkout settings section.

![Screenshot of Klarna Checkout settings](src/images/klarna_checkout.png)

### Known issues

Right after press "Place Order" button client see the page with message
`{"url":"https:\/\/apitest.vipps.no\/dwo-api-application\/v1\/****"}`

**Solution:** Update your Vipps module to the latest version.

## How to enable debug mode / requests profiling

If you have experienced any issue with Vipps try to enable `Request Profiling` and `Debug` features under vipps payment configuration area:

`Stores -> Configuration -> Sales -> Payment Methods -> Vipps`

![Screenshot of Vipps Configuration Area](src/images/vipps_basic_v2.png)

After that, all information related to vipps payment module will be stored into two files `{project_root}/var/log/vipps_exception.log` or `{project_root}/var/log/vipps_debug.log`.

Requests Profiling is a page in Magento admin panel that helps you to track a communication between Vipps and Magento.
You can find the page under `System -> Vipps`

![Screenshot of Request Profiling Grid](src/images/request_profiling.png)

On the page you can see the list of all requests for all orders that Magento sends to Vipps.
By clicking on a link `Show` in an `Action` column of grid you can find appropriate response from Vipps.

Using built-in Magento grid filter you could find all requests per order that you are interested in.

## Quote Monitoring Tool

From 1.2.1 version we released Quote Monitoring.

It simplifies detection of failed order placement and identifies the root causes in cases of failure.

The monitoring tool is located under **System -> Vipps Payment -> Quote Monitoring**.
This page displays all orders that were attempted to be placed.
Each record in the list provides detailed information about order creation flow: current status, list of attempts, each attempt results.

Monitoring quote statuses explanation:

New - payment is initiated on the Vipps side.

Processing - Magento has started processing for initiated payment.

Placed - The order has been placed.

Expired - The customer has not approved payment for some time.

Placement Failed - All attempts were unsuccessful.

Canceled - The payment has been canceled (Cancellation can be initiated by the customer in Vipps or manually/automatically by Magento for Quotes in Placement Failed status)

Cancel Failed - Means failed to cancel payment. Record in this status require admin/developer interaction.

## Tax Calculation for Express Checkout
When enabling the Express checkout payment in the configuration area you may see a notification at the top of admin panel saying:
![Express Checkout notice](src/images/express-checkout-notice.png)

This means that you should change Tax Calculation Settings to be based on **Shipping Origin**:
![Tax Calculation Settings](src/images/tax-origin-settings.png)

Otherwise an issue with calculating delivery cost might occur.
