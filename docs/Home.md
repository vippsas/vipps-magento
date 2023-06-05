## Vipps Payment Module for Magento 2

Vipps Payment offers a familiar and convenient buying experience that can help your customers spend more time shopping and less time checking out.

Vipps Payment is used by large and small companies.  From years of shopping safely with Vipps, customers trust their personal information will remain secure.  Businesses have the reassurance of our advanced fraud protection and payment protection policy.

For more information about Vipps Payment and Magento 2, please visit our [Vipps Payment for Magento](https://www.vipps.no/bedrift/vipps-pa-nett) site.

See also the Magento documentation for order management: https://docs.magento.com/m2/ce/user_guide/sales/order-management.html

## Requirements/Pre-requisites

* Magento 2.2+
   * [Magento 2 System Requirements](http://devdocs.magento.com/magento-system-requirements.html)
* SSL is installed on your site and active on the Checkout page
* Supported protocols HTTP1/HTTP1.1
   * Magento relies on the [Zend Framework](https://framework.zend.com), which does not support HTTP/2.
   * HTTP/1.1 must therefore be "forced", typically by using [CPanel](https://documentation.cpanel.net/display/EA4/Apache+Module%3A+HTTP2) or similar.
* A verified Vipps Payment merchant account - [sign up here](https://portal.vipps.no/register/vippspanett)

## Installation

1. Navigate to your [Magento root directory](https://devdocs.magento.com/guides/v2.2/extension-dev-guide/build/module-file-structure.html).
1. Enter command: `composer require vipps/module-payment`
1. Enter command: `php bin/magento module:enable Vipps_Payment` 
1. Enter command: `php bin/magento setup:upgrade`
1. Put your Magento in production mode if it’s required.

## Configuration

The Vipps Payment module can be easily configured to meet business expectations of your web store. This section will show you how to configure the extension via `Magento Admin`.

From Magento Admin navigate to `Store` -> `Configuration` -> `Sales` -> `Payment Methods` section. On the Payments Methods page the Vipps Payments method should be listed together with other installed payment methods in a system.

By clicking the `Configure` button, all configuration module settings will be shown. Once you have finished with the configuration simply click `Close` and `Save` button for your convenience.

## Documentation

Please find more information on the [Documentation](https://github.com/vippsas/vipps-magento/wiki/Documentation) page

## FAQ

Please follow the info in [FAQ](https://github.com/vippsas/vipps-magento/wiki/FAQ)

## Support

If you discover any bugs, technical issues or you simply have a feature request, please create a [GitHub issue](https://github.com/vippsas/vipps-magento/issues/new).

Magento is an open-source e-commerce platform written in PHP: https://magento.com

For Magento support, please see the Magento Help Center: https://support.magento.com/hc/en-us

Magento Inc is an Adobe company: https://magento.com/about


Please follow this [instruction](https://github.com/vippsas/vipps-developers/blob/master/contact.md) to contact us.

See the Vipps Developers repository for Vipps contact information, etc: https://github.com/vippsas/vipps-developers

Always include a  description of the problem detailed

## License 

MIT license. For more information, please check the [LICENSE](https://github.com/vippsas/vipps-magento/wiki/LICENSE) file
