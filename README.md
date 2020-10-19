# Vipps Payment Module for Magento 2

## About Vipps Payment for Magento 2

Vipps Payment offers a familiar and convenient buying experience that can help your customers spend more time shopping and less time checking out.

Vipps Payment is used by large and small companies.  From years of shopping safely with Vipps, customers trust their personal information will remain secure.  Businesses have the reassurance of our advanced fraud protection and payment protection policy.

For more information about Vipps Payment and Magento 2, please visit our [Vipps Payment for Magento](https://www.vipps.no/bedrift/vipps-pa-nett) site.

See also the Magento documentation for order management: https://docs.magento.com/m2/ce/user_guide/sales/order-management.html

### Magento 1

Please see: https://github.com/vippsas/vipps-magento-v1

## Prerequisites
* Magento 2.2.x, Mageton 2.3.x, Magento 2.4.x
   * [Magento 2 System Requirements](http://devdocs.magento.com/magento-system-requirements.html)
* SSL is installed on your site and active on the Checkout page
* Supported protocols HTTP1/HTTP1.1
   * Magento relies on the [Zend Framework](https://framework.zend.com), which does not support HTTP/2.
   * HTTP/1.1 must therefore be "forced", typically by using [CPanel](https://documentation.cpanel.net/display/EA4/Apache+Module%3A+HTTP2) or similar.
* A verified Vipps Payment merchant account - [sign up here](https://vippsbedrift.no/signup/vippspanett/)

## Installation and Configuration

Please follow the instructions in [INSTALL.md](INSTALL.md)


### Quote Processing Flow

1. When payment was initiated a new record is created on Vipps Quote Monitoring page with status `New`.
1. Magento polls Vipps for orders to process by cron.
1. When order was accepted on Vipps side, Magento is trying to place an order and marks a record as `Placed`
1. When order was cancelled on Vipps side, Magento marks such record as `Cancelled`
1. If order has not been accepted on Vipps side within some period of time so it marked as expired, Magento marks it as `Expired`
1. If order has not been yet accepted on Vipps side and has not been expired yet, Magento marks it as `Processing`. Appropriate message added on record details page.
1. If order accepted on Vipps side but an error occurred during order placement on Magento side, such record marks as `Processing`. Appropriate message added on record details page.
1. Magento is trying to process the same record `3` times and when it failed after `3` times such record marks as `Place Failed`.
1. It is possible to specify that Magento has to cancel Vipps order automatically when appropriate Magento quote was failed so that client's money released. See `Store -> Sales -> Payment Methods -> Vipps -> Cancellation`
1. If it is specified that Magento has to cancel all failed quotes then Magento fetches all records marked as `Place Failed`, cancel them and marks as `Cancelled`

Here is a diagram of the process
![Screenshot of Quote Processing Flow](docs/images/quote-monitoring-flow.png)


# Requests Profiling

Requests Profiling is a page in Magento admin panel that helps you to track a communication between Vipps and Magento.
You can find the page under `System -> Vipps`

On the page you can see the list of all requests for all orders that Magento sends to Vipps. 
By clicking on a link `Show` in an `Action` column of grid you can find appropriate response from Vipps.

Using built-in Magento grid filter you could easily find all requests per order that you are interested in.


# Magento

Magento is an open-source e-commerce platform written in PHP: https://magento.com

For Magento support, please see the Magento Help Center: https://support.magento.com/hc/en-us

Magento Inc is an Adobe company: https://magento.com/about.

# Vipps contact information

Please follow this [instruction](https://github.com/vippsas/vipps-developers/blob/master/contact.md) to contact us.

For plugin related issues please contact vipps@vaimo.no or submit an issue on GitHub [for Magento 2](https://github.com/vippsas/vipps-magento).

# Documentation

Please see our [Documentation](DOCUMENTATION.md)

# FAQ

Please see our [FAQ section](FAQ.md)
