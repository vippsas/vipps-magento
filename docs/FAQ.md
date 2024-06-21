<!-- START_METADATA
---
title: Vipps/MobilePay Payment Module for Adobe Commerce FAQ
sidebar_label: FAQ
sidebar_position: 50
pagination_next: null
pagination_prev: null
---
END_METADATA -->

# Frequently asked questions


## In which countries can I use Vipps MobilePay?

You can get paid by users who have Vipps in Norway, or who have MobilePay in Denmark or Finland.

## For how long is an order reserved?

:::note
Payments initiated in Finland and Denmark have only 14 days to be captured; while
payments in Norway have 180 days.
If the payments aren't captured within this time, they will be automatically cancelled.

Payments can only be captured up to 14 days (MobilePay) or 180 days (Vipps) after reservation.
See [Reserve and capture](https://developer.vippsmobilepay.com/docs/knowledge-base/reserve-and-capture/).
:::


When a payment is completed with Vipps MobilePay, the money will be reserved, but only transferred to the merchant when the order is set to “Complete” or the money is captured manually. For MobilePay, this reservation period is 14 days, so you will need to ship and fulfill orders before this; or to make an agreement with the customer to capture the money before this period is over. For Vipps, the period is 180 days. For payments made by credit card in Vipps/MobilePay Checkout, the period can again be as short as 7 days.

## How do I capture an order?

When *Payment Action* is set to *Authorize* and *Capture*, the invoice is created automatically in Adobe Commerce. In such a case, the *Invoice* button does not appear, and the order is ready to ship.
For more details about capturing orders, refer to [Adobe Commerce: Creating an Invoice](https://experienceleague.adobe.com/en/docs/commerce-admin/stores-sales/order-management/invoices#create-an-invoice).

## How do I partially capture an order?

Visit the invoice page of your order by clicking the *Invoice* button on the order page. In the *Items to Invoice* section, update the *Qty to Invoice* column to include only specific items on the invoice.
Then, click *Update Qty’s* and submit *Invoice*.

## How do I cancel an order?

In Adobe Commerce, an order can be cancelled in the case where all invoices and shipments have been returned, and the Vipps MobilePay payment transaction has not been captured.
Otherwise, the refund should be finished first. This Vipps/MobilePay Payment module supports offline partial cancellation. It is used to cancel separate order items.

## How do I refund an order?

For orders refunding Adobe Commerce propose [Adobe Commerce: Credit Memo](https://experienceleague.adobe.com/en/docs/commerce-admin/stores-sales/order-management/credit-memos/credit-memos) functionality.
Credit Memo allows you to make a refund for captured transaction.

## How do I partially refund an order?

You can partially refund an order by specifying *Items to Refund* on the *Credit Memo* page and updating the *Qty to Refund* field.

## How can I get help with the Vipps/MobilePay Payment module?

*Vipps/MobilePay Payment Module for Adobe Commerce* is developed by [Vaimo](https://www.vaimo.com), and the same developers who made
the Vipps/MobilePay Payment module also help with improvements, maintenance and developer assistance.

If you are having a problem, please make sure that you are using the latest version:
[https://github.com/vippsas/vipps-magento/releases](https://github.com/vippsas/vipps-magento/releases)

The best way to report a problem (or ask a question) is to use GitHub's built-in "issue" functionality:
[Issues](https://github.com/vippsas/vipps-magento/issues).

### How can I get help with Vipps or MobilePay (unrelated to Adobe Commerce)?

See: [How to contact Vipps MobilePay](https://developer.vippsmobilepay.com/docs/contact/).

## Why does it take so long after purchase before orders are created in Adobe Commerce?

Vipps/MobilePay depends on a proper cron job configuration for background processing of orders.
Failure to set it up properly means it will not function as expected.

## Why are some orders missing in Adobe Commerce?

This scenario is possible for express payment flow. Unlike of regular payment, order is not created before redirecting to
Vipps Landing page. If the transaction was successfully initiated by client, a new record
with a quote ID and reserved order ID will be created in DB table `vipps_quote`. This can be helpful to find a status of a transaction.

## How do I enable Vipps Payment for Klarna Checkout

Select Vipps from the list of external payment methods in the appropriate Klarna checkout settings section.

![Screenshot of Klarna checkout settings](images/klarna_checkout.png)

### Why am I seeing a strange page with URL printed?

Right after pressing *Place Order*, the client may see the page with message
`{"url":"https:\/\/apitest.vipps.no\/dwo-api-application\/v1\/****"}`

**Solution:** Update your Vipps/MobilePay Payment module to the latest version.

## How to enable debug mode / requests profiling

If you have experienced any issue with Vipps MobilePay, try to enable *Request Profiling* and *Debug* features under the payment configuration area:

*Stores -> Configuration -> Sales -> Payment Methods -> Vipps MobilePay*

![Screenshot of Configuration Area](images/vipps_basic_v2.png)

After that, all information related to the Vipps/MobilePay payment module will be stored into two files `{project_root}/var/log/vipps_exception.log` or `{project_root}/var/log/vipps_debug.log`.

*Requests Profiling* is a page in the Adobe Commerce admin panel that helps you to track a communication between Vipps MobilePay and Adobe Commerce.
You can find the page under `System -> Vipps Payment -> Requests Profiling`

![Screenshot of Request Profiling Grid](images/request_profiling.png)

On the page, you can see the list of all requests for all orders that Adobe Commerce sends to Vipps MobilePay.
By clicking *Show* in an *Action* column of grid, you can find appropriate response from Vipps MobilePay.

Using the built-in Adobe Commerce grid filter, you can find all the requests for an order you're interested in.

