# Vipps Payment Module for Magento 2: Installation

# Prerequisites

1. [Magento 2.4.x](https://devdocs.magento.com/guides/v2.4/release-notes/bk-release-notes.html)
1. SSL must be installed on your site and active on your Checkout pages.
1. You must have a Vipps merchant account. See [Vipps på Nett](https://www.vipps.no/bedrift/vipps-pa-nett)
1. As with _all_ Magento extensions, it is highly recommended to backup your site before installation and to install and test on a staging environment prior to production deployments.

# Installation via Composer

1. Navigate to your [Magento root directory](https://devdocs.magento.com/guides/v2.4/extension-dev-guide/build/module-file-structure.html).
1. Enter command: `composer require vipps/module-payment`
1. Enter command: `php bin/magento module:enable Vipps_Payment` 
1. Enter command: `php bin/magento setup:upgrade`
1. Put your Magento in production mode if it’s required.

# Configuration

The Vipps Payment module can be easily configured to meet business expectations of your web store. This section will show you how to configure the extension via `Magento Admin`.

From Magento Admin navigate to `Store` -> `Configuration` -> `Sales` -> `Payment Methods` section. On the Payments Methods page the Vipps Payments method should be listed together with other installed payment methods in a system.

By clicking the `Configure` button, all configuration module settings will be shown. Once you have finished with the configuration simply click `Close` and `Save` button for your convenience.

## Add a separate connection for Vipps resources
These settings are required to prevent profiles loss when Magento reverts invoice/refund transactions.
* Duplicate 'default' connection in app/etc/env.php and name it 'vipps'. It should look like:
```         
         'vipps' =>
         array (
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
```
   'vipps' =>
   array (
      'connection' => 'vipps',
   ),
```

# Settings

Vipps Payments configuration is divided by sections. It helps to quickly find and manage settings of each module feature:

1. Basic Vipps Settings.
1. Express Checkout Settings.

![Screenshot of Vipps Settings](docs/images/vipps_method.png)

Please ensure you check all configuration settings prior to using Vipps Payment. Pay attention to the Vipps Basic Settings section, namely `Saleunit Serial Number`, `Client ID`, `Client Secret`, `Subscription Key 1`, `Subscription Key 2`.

For information about how to find the above values, see the [Vipps Developer Portal documentation](https://github.com/vippsas/vipps-developers/blob/master/vipps-developer-portal-getting-started.md).

# Basic Vipps Settings

![Screenshot of Basic Vipps Settings](docs/images/vipps_basic.png)

# Express Checkout Settings

![Screenshot of Express Vipps Settings](docs/images/express_vipps_settings.png)

# Quote Monitoring

Quote it is a cart contents in Magento. Theoretically the quote is an offer and if the user accepts it (by checking out) it converts to order.

When payment was initiated (customer was redirected to Vipps) Magento creates a new record on `Vipps Quote Monitoring` page and starts tracking an Vipps order.
To do that Magento has a cron job that runs by schedule/each 10 min.

You can find this page under `System -> Vipps` menu section. Under `Store -> Sales -> Payment Methods -> Vipps -> Cancellation` you can find appropriate configuration settings.

# Support

Magento is an open source ecommerce solution: https://magento.com

Magento Inc is an Adobe company: https://magento.com/about

For Magento support, see Magento Help Center: https://support.magento.com/hc/en-us

Vipps has a dedicated team ready to help: magento@vipps.no
