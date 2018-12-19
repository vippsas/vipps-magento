# Vipps for Magento: Frequently Asked questions

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

This depends on status of the cron job configured in Magento.
If there was a problem with cron job, (insert understandable text here)

## Why are some orders missing in Magento?

We are working on improved Vipps Monitoring, to solve such issues.
Until this is released, we recommend analyzing the log files and try to find some error related to this order.
Log files are located in the root Magento folder: `./var/log/vipps_debug.log` and `./var/log/vipps_exception.log`.
