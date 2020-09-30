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

We are working on improved Vipps Monitoring, to solve such issues.
Until this is released, we recommend analyzing the log files and try to find some error related to this order.
Log files are located in the root Magento folder: `./var/log/vipps_debug.log` and `./var/log/vipps_exception.log`.

It is also possible to find appropriate quote in Magento database but it is required to be familiarized with MySQL.
Here is a MySQL query that you can execute to find missed order:

```
SELECT IF (t1.reserved_order_id is not null, 
           t1.reserved_order_id, 
           SUBSTRING(t2.additional_information, LOCATE('reserved_order_id', t2.additional_information) + 20, 9)) 
           AS order_id, t2.additional_information 
FROM quote t1
     inner join quote_payment t2 on t1.entity_id = t2.`quote_id`
WHERE (t1.reserved_order_id = '{insert_order_id_here}' 
       or t2.additional_information like '%{insert_order_id_here}%')
```
 
*You have to replace '{insert_order_id_here}' with your real order id.
 
The result output contains two columns `order_id` and `additional_information` that should be analyzed to find a reason why the order was not placed on Magento side.

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


## Tax Calculation for Express Checkout
When enabling the Express checkout payment in the configuration area you may see a notification at the top of admin panel saying:
![Express Checkout notice](docs/images/express-checkout-notice.png)

This means that you should change Tax Calculation Settings to be based on **Shipping Origin**:
![Tax Calculation Settings](docs/images/tax-origin-settings.png)

Otherwise an issue with calculating delivery cost might occur.
