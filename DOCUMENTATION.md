# Prerequisites

1. Magento 2 installed ([Magento 2.2.x](https://devdocs.magento.com/guides/v2.2/release-notes/bk-release-notes.html), [Magento 2.3.x](https://devdocs.magento.com/guides/v2.3/release-notes/bk-release-notes.html), [Magento 2.4.x](https://devdocs.magento.com/guides/v2.4/release-notes/bk-release-notes.html))
1. SSL must be installed on your site and active on your Checkout pages.
1. You must have a Vipps merchant account. See [Vipps på Nett](https://www.vipps.no/bedrift/vipps-pa-nett)
1. As with *all* Magento extensions, it is highly recommended backing up your site before installation and to install and test on a staging environment prior to production deployments.

## Installation

### Installation via Composer

1. Navigate to your [Magento root directory](https://devdocs.magento.com/guides/v2.4/extension-dev-guide/build/module-file-structure.html).
1. Enter command: `composer require vipps/module-payment`
1. Enter command: `php bin/magento module:enable Vipps_Payment`
1. Enter command: `php bin/magento setup:upgrade`
1. Put your Magento in production mode if it’s required.

### Installation via Marketplace

Here are steps required to install Payments extension via Component Manager.

1. Make a purchase for the Vipps extension on [Magento Marketplace](https://marketplace.magento.com/vipps-module-payment.html).
1. From your Magento Admin, access *System* > *Web Setup Wizard* page.
1. Enter Marketplace authentication keys. Please read about authentication keys generation.
1. Navigate to *Component Manager* page.
1. On the *Component Manager* page, click the *Sync* button to update your new purchased extensions.
6. Click *Install* in the *Action* column for Realex Payments component.
7. Follow *Web Setup* Wizard instructions.

## Configuration

The Vipps Payment module can be easily configured to meet business expectations of your web store. This section will show you how to configure the extension via `Magento Admin Panel`.

1. From Magento *Admin*, navigate to *Store* > *Configuration* > *Sales* > *Payment Methods* section.
1. On the *Payments Methods* page, the *Vipps Payments* method should be listed together with other installed payment methods in a system.
1. By clicking the *Configure* button, all configuration module settings will be shown.
1. Once you have finished with the configuration, click *Close* and *Save* button.
1. [Clear Magento Cache](https://devdocs.magento.com/guides/v2.4/config-guide/cli/config-cli-subcommands-cache.html).

### Add a separate connection for Vipps resources

These settings are required to prevent profiles loss when Magento reverts invoice/refund transactions.  

* Duplicate 'default' connection in `app/etc/env.php` and name it 'vipps'. It should look like:

```php
         'vipps' => array (
             'host' => 'your_DB_host',
             'dbname' => 'your_DB_name',
             'username' => 'your_user',
             'password' => 'your_password',
             'model' => 'mysql4',
             'engine' => 'innodb',
             'initStatements' => 'SET NAMES utf8;',
             'active' => '1',
         ),
```

* Then, add the following configuration to the `resource` array in the same file:

```php
         'vipps' => array (
             'connection' => 'vipps',
         ),
```

### Enable debug mode / requests profiling

If you have experienced any issue with Vipps, try to enable *Request Profiling* and *Debug* features in the Vipps payment configuration area: *Stores* > *Configuration* > *Sales* > *Payment Methods* > *Vipps*.

![Screenshot of Vipps Configuration Area](docs/images/vipps_basic.png)


*Requests Profiling* is a page in the Magento admin panel that helps you to track a communication between Vipps and Magento.
You can find the page under *System* > *Vipps*.

![Screenshot of Request Profiling Grid](docs/images/request_profiling.png)

On the page, you can see the list of all requests for all orders that Magento sends to Vipps.
By clicking on *Show* in the *Action* column of the grid, you can find the appropriate response from Vipps.

By using the built-in Magento grid filter, you can find all requests per order that you are interested in.

Logs which are related to the Vipps payment module are located under two files:

* `{project_root}/var/log/vipps_exception.log`
* `{project_root}/var/log/vipps_debug.log`

## Settings

The payments configuration settings are divided into these sections:

1. Basic Vipps Settings
1. Express Checkout Settings
1. Additional Settings

![Screenshot of Vipps Settings](docs/images/vipps_method.png)

Ensure that you check all configuration settings before using Vipps Payment. Pay special attention to the Vipps Basic Settings section, namely *Saleunit Serial Number*, *Client ID*, *Client Secret*, *Subscription Key*.

For information about how to find the above values, see the [Vipps Developer documentation](https://developer.vippsmobilepay.com/).

### Basic Vipps Settings

![Screenshot of Basic Vipps Settings](docs/images/vipps_basic.png)

* *Environment*  - Vipps API mode, which can be *Production* or *Develop*.
* *Payment Action* - *Authorize* (process authorization transaction; funds are blocked on customer account, but not withdrawn) or *Capture* (withdraw previously authorized amount).
* *Order Status* - Default order status before redirecting back to Magento. Can be *Pending* or *Payment Review*.
* *Debug* - Log all actions with Vipps Payment module into `{project_root}/var/log/vipps_debug.log` file *(not recommended in production mode)*.
* *Request/Response Profiling* - Log all requests/responses to Vipps API into `vipps_profiling` table.

### Express Checkout Settings

![Screenshot of Express Vipps Settings](docs/images/express_vipps_settings.png)

### Additional Settings

![Screenshot of Vipps Additional Settings](docs/images/vipps_additional_settings.png)

* *Processing type* - Manually or automatically cancel quote.
* *Enable Partial Void* - Allow cancellation for captured (not refunded) transaction (mostly used to cancel order item).

## Quote Monitoring

Quote cart contents in Magento. Theoretically, the quote is an offer and if the user accepts it (by checking out) it converts to order.

When payment has been initiated (customer redirected to Vipps) Magento creates a new record on `Vipps Quote Monitoring` page and starts tracking a Vipps order.
To do that Magento has a cron job that runs by schedule/each 10 min.

You can find this page under the *System* > *Vipps* menu. Under *Store* > *Sales* > *Payment Methods* > *Vipps* > *Cancellation*, you can find appropriate configuration settings.

## Order handling

Please refer to the Magento official documentation to learn more about [order processing](https://docs.magento.com/user-guide/sales/order-processing.html).

### How do I capture an order?

When *Payment Action* is set to *Authorize* and *Capture*, the invoice is created automatically in Magento. In such a case, the *Invoice* button does not appear, and the order is ready to ship.
For more details about capturing orders, refer to [Creating an Invoice documentation](https://docs.magento.com/user-guide/sales/invoice-create.html).

### How do I partially capture an order?

Visit the invoice page of your order by clicking the *Invoice* button on the order page. In the *Items to Invoice* section, update the *Qty to Invoice* column to include only specific items on the invoice.
Then, click *Update Qty’s* and submit *Invoice*.

### How do I cancel an order?

In Magento, an order can be canceled in the case where all invoices and shipments have been returned and the Vipps Payment transaction has not been captured.
Otherwise, the refund should be finished first. The Vipps Payment module supports offline partial cancellation. It is used to cancel separate order items.

### How do I refund an order?
For orders refunding Magento propose [Credit Memo](https://docs.magento.com/user-guide/sales/credit-memos.html) functionality.
Credit Memo allows to make a refund for captured transaction.

### How do I partially refund an order?
It can be done by specifying `Items to Refund` on `Credit Memo` page and updating `Qty to Refund` field.
