# Vipps Payment Module for Magento 2: Installation

## Prerequisites

1. Magento 2 installed ([Magento 2.2.x](https://devdocs.magento.com/guides/v2.2/release-notes/bk-release-notes.html), [Magento 2.3.x](https://devdocs.magento.com/guides/v2.3/release-notes/bk-release-notes.html), [Magento 2.4.x](https://devdocs.magento.com/guides/v2.4/release-notes/bk-release-notes.html))
1. SSL must be installed on your site and active on your Checkout pages.
1. You must have a Vipps merchant account. See [Vipps på Nett](https://www.vipps.no/bedrift/vipps-pa-nett)
1. As with _all_ Magento extensions, it is highly recommended to back up your site before installation and to install and test on a staging environment prior to production deployments.

## Installation via Composer

1. Navigate to your [Magento root directory](https://devdocs.magento.com/guides/v2.4/extension-dev-guide/build/module-file-structure.html).
1. Enter command: `composer require vipps/module-payment`
1. Enter command: `php bin/magento module:enable Vipps_Payment`
1. Enter command: `php bin/magento setup:upgrade`
1. Put your Magento in production mode if it’s required.

## Configuration

The Vipps Payment module can be easily configured to meet business expectations of your web store. This section will show you how to configure the extension via `Magento Admin Panel`.

1. From Magento Admin navigate to `Store` -> `Configuration` -> `Sales` -> `Payment Methods` section.
1. On the Payments Methods page the Vipps Payments method should be listed together with other installed payment methods in a system.
1. By clicking the `Configure` button, all configuration module settings will be shown.
1. Once you have finished with the configuration simply click `Close` and `Save` button for your convenience.
1. [Clear Magento Cache.](https://devdocs.magento.com/guides/v2.4/config-guide/cli/config-cli-subcommands-cache.html)

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

* Add also the following configuration to 'resource' array in the same file:

```php
         'vipps' => array (
             'connection' => 'vipps',
         ),
```

## Settings

Vipps Payments configuration is divided by sections. It helps to quickly find and manage settings of each module feature:

1. Basic Vipps Settings.
1. Express Checkout Settings.
1. Additional Settings.

![Screenshot of Vipps Settings](docs/images/vipps_method.png)

Please ensure you check all configuration settings prior to using Vipps Payment. Pay attention to the Vipps Basic Settings section, namely `Saleunit Serial Number`, `Client ID`, `Client Secret`, `Subscription Key`.

For information about how to find the above values, see the [Vipps Developer Portal documentation](https://developer.vippsmobilepay.com/).

## Basic Vipps Settings

![Screenshot of Basic Vipps Settings](docs/images/vipps_basic.png)

**Environment**  - Vipps API mode. Can be *production/develop*.
**Payment Action** - *Authorize*(process authorization transaction; funds are blocked on customer account, but not withdrawn) or *Capture* (withdraw previously authorized amount).
**Order Status** - default order status before redirecting back to Magento. Can be *Pending* or *Payment Review*.
**Debug** - log all actions with Vipps Payment module into `{project_root}/var/log/vipps_debug.log` file *(not recommended in production mode)*.
**Request/Response Profiling** - log all requests/responses to Vipps API into `vipps_profiling` table.

## Express Checkout Settings

![Screenshot of Express Vipps Settings](docs/images/express_vipps_settings.png)

## Additional Settings

![Screenshot of Vipps Additional Settings](docs/images/vipps_additional_settings.png)


**Process type** - whether cancel quote automatically or not.
**Enable Partial Void** - allow cancellation for captured(not refunded) transaction (mostly used to cancel order item).


## Quote Monitoring

Quote is for cart contents in Magento. Theoretically, the quote is an offer and if the user accepts it (by checking out) it converts to order.

When payment is initiated (customer is redirected to Vipps), Magento creates a new record on `Vipps Quote Monitoring` page and starts tracking an Vipps order.
To do that, Magento has a cron job that runs by schedule, each 10 minutes.

You can find this page under `System -> Vipps` menu section. Under `Store -> Sales -> Payment Methods -> Vipps -> Cancellation`, you can find appropriate configuration settings.

## Support

Magento is an open source ecommerce solution: https://magento.com

Magento Inc is an Adobe company: https://magento.com/about

For Magento support, see Magento Help Center: https://support.magento.com/hc/en-us

Vipps has a dedicated team ready to help: magento@vipps.no
