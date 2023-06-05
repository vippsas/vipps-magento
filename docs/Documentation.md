
## Table of contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Add a separate connection for Vipps resources](#add-a-separate-connection-for-vipps-resources)
- [Enable debug mode / requests profiling](#enable-debug-mode--requests-profiling)
- [Settings](#settings)
- [Basic Vipps Settings](#basic-vipps-settings)
- [Checkout Settings](#checkout-settings)
- [Express Checkout Settings](#express-checkout-settings)
- [Cancellation](#cancellation-settings)
- [Order processing](#order-processing)
- [Quote Processing Flow](#quote-processing-flow)
- [Requests Profiling](#requests-profiling)
- [Additional info](#additional-info)


### Installation

1. Navigate to your [Magento root directory](https://devdocs.magento.com/guides/v2.2/extension-dev-guide/build/module-file-structure.html).
1. Enter command: `composer require vipps/module-payment`
1. Enter command: `php bin/magento module:enable Vipps_Payment`
1. Enter command: `php bin/magento setup:upgrade`
1. Put your Magento in production mode if it’s required.

### Configuration

The Vipps Payment module can be easily configured to meet business expectations of your web store. This section will show you how to configure the extension via `Magento Admin`.

From Magento Admin navigate to `Store` -> `Configuration` -> `Sales` -> `Payment Methods` section. On the Payments Methods page the Vipps Payments method should be listed together with other installed payment methods in a system.

By clicking the `Configure` button, all configuration module settings will be shown. Once you have finished with the configuration simply click `Close` and `Save` button for your convenience.

### Add a separate connection for Vipps resources
These settings are required to prevent profiles loss when Magento reverts invoice/refund transactions.
* Duplicate 'default' connection in app/etc/env.php and name it 'vipps'. It should look like:
```
'vipps' => [
    'host' => 'your_DB_host',
    'dbname' => 'your_DB_name',
    'username' => 'your_user',
    'password' => 'your_password',
    'model' => 'mysql4',
    'engine' => 'innodb',
    'initStatements' => 'SET NAMES utf8;',
    'active' => '1',
],
```
* Add also the following configuration to 'resource' array in the same file:
```
'vipps' => [
    'connection' => 'vipps',
],
```

### Enable debug mode / requests profiling

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

### Settings

Vipps Payments configuration is divided by sections. It helps to quickly find and manage settings of each module feature:

1. Basic Vipps Settings.
1. Express Checkout Settings.

![Screenshot of Vipps Settings](src/images/vipps_method.png)

Please ensure you check all configuration settings prior to using Vipps Payment.
Pay attention to the Vipps Basic Settings section, namely `Saleunit Serial Number`, `Client ID`, `Client Secret`, `Subscription Key 1`, `Subscription Key 2`.

For information about how to find the above values, see the [Vipps Portal documentation](https://github.com/vippsas/vipps-developers/blob/master/vipps-getting-started.md#getting-the-api-keys).

### Basic Vipps Settings

![Screenshot of Basic Vipps Settings](src/images/vipps_basic_v2.png)


### Checkout Settings

Vipps payment will be unavailable when any of methods is selected on checkout. Also chosen methods will be unavailable on Vipps Express Checkout page.

![Screenshot of Checkout Settings](src/images/checkout_settings.png)


### Express Checkout Settings

![Screenshot of Express Vipps Settings](src/images/express_vipps_settings.png)

#### Cancellation Settings

Here you could configure following settings:

 - "Cart Persistence", if set to "Yes", in case when client cancel an order on Vipps side, cart contains recently added products.
 - "Number of Attempts", number of failed attempts to place an order before order will be canceled.
 - "Storage Period", number of days to store quotes information. 0 value to keep all records.
 - "Inactivity Time", developers only. Number of minutes that customer is idle before Vipps order will be canceled in Magento.
 - "Processing Type", deprecated settings that will be removed in future releases. (should be set to "Automatic")

![Screenshot of Checkout Settings](src/images/cancellation_settings.png)

### Order processing

Please refer to Magento official documentation to learn more about [order processing](https://docs.magento.com/user-guide/sales/order-processing.html).
See also the Magento documentation for [order management](https://docs.magento.com/m2/ce/user_guide/sales/order-management.html).



### Quote Processing Flow

1. When payment was initiated a new record is created on Vipps Quote Monitoring page with status `New`.
   - In case of "Regular Payment" order immediately placed on Magento side with status "new/pending/payment review" (depends on appropriate configuration)

1. Magento polls Vipps for orders to process by cron.
1. When order was accepted on Vipps side, Magento is trying to place an order and marks a record as `Placed`
   - In case of "Regular Payment" Magento order moved to status "processing"

1. When order was cancelled on Vipps side, Magento marks such record as `Cancelled`
   - Cancel order on Magento side if it was previously placed.

1. If order has not been accepted on Vipps side within some period of time so it marked as expired, Magento marks it as `Expired`
   - Cancel order on Magento side if it was placed before.

1. If order has not been yet accepted on Vipps side and has not been expired yet, Magento marks it as `Processing`. Appropriate message added on record details page.
1. If order accepted on Vipps side but an error occurred during order placement on Magento side, such record marks as `Processing`. Appropriate message added on record details page.
1. Magento is trying to process the same record `3` times and when it failed after `3` times such record marks as `Place Failed`.
1. It is possible to specify that Magento has to cancel Vipps order automatically when appropriate Magento quote was failed so that client's money released. See `Store -> Sales -> Payment Methods -> Vipps -> Cancellation`
1. If it is specified that Magento has to cancel all failed quotes then Magento fetches all records marked as `Place Failed`, cancel them and marks as `Cancelled`

Here is a diagram of the process

![Screenshot of Quote Processing Flow](src/images/quote-monitoring-flow.png)


### Requests Profiling

Requests Profiling is a page in Magento admin panel that helps you to track a communication between Vipps and Magento.
You can find the page under `System -> Vipps`

On the page you can see the list of all requests for all orders that Magento sends to Vipps.
By clicking on a link `Show` in an `Action` column of grid you can find appropriate response from Vipps.

Using built-in Magento grid filter you could easily find all requests per order that you are interested in.



### Additional info

Please contact by [support](https://github.com/vippsas/vipps-magento/wiki)
