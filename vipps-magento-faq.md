# Vipps for Magento: Frequently Asked questions

## Why does it take so long after purchase before orders are created in Magento?

This depends on status of the cron job configured in Magento.
If there was a problem with cron job, (insert understandable text here)

## Why are some orders missing in Magento?

We are working on improved Vipps Monitoring, to solve such issues.
Until this is released, we recommend analyzing the log files and try to find some error related to this order.
Log files are located in the root Magento folder: `./var/log/vipps_debug.log` and `./var/log/vipps_exception.log`.
