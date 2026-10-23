<!-- START_METADATA
---
title: Changelog
sidebar_position: 200
pagination_next: null
section: Plugins
---
END_METADATA -->

# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog and this project adheres to Semantic Versioning.


## [3.1.0] - 2026-10-05

### Added
- Support for Vipps Widget SDK for express button https://developer.vippsmobilepay.com/docs/knowledge-base/widget

### Changed
- A cart now carries at most one live Express payment. Starting Express again terminates the cart's
  earlier payment at Vipps/MobilePay while it is still unapproved, instead of leaving the shopper able to
  approve both and hold two reservations on their card. An approved payment is never terminated.
- A payment the shopper has already approved is turned into its order straight away: when they start
  Express again, or when they come back to the store, rather than at the next order fetch. They are sent
  to the order confirmation, or told it is being completed, instead of being offered a second payment.
- An Express payment the shopper has not approved yet can be read again. Its user and shipping details are
  absent until approval, and building them from nothing threw, so the payment could not be inspected,
  cancelled or picked up by the order fetch until the shopper approved it.
- Closing, cancelling or approving in the Express widget now ends in the shopper's own session: the cart is
  restored with the cancellation message, or the order confirmation shown.
- Starting Express with no cart in the session, for example right after an order, no longer fails with a
  server error. The shopper is sent to the cart page with "Your cart is empty."

## [3.0.5] - 2026-10-23

### Fixed
- Regression in 3.0.4 for regular checkout using vipps payment method (not express) had a
  check against two orders for one cart counted that cancelled order, so the new payment was refused and
  only released again by the cancellation cron.

### Security
- Returning from Vipps/MobilePay now requires a single-use token that is generated when the payment
  starts and carried in the return URL. The token expires after 24 hours and is used up once the return has placed the order
  or restored the cart; a return that could not be resolved yet can be retried with the same link.
- `vippsActivateCart` only reactivates the cart of a payment that was cancelled or has expired, and only for
  the customer who owns it. Reported by Michael Stochl of ICT IronByte.
- `vippsGetPaymentDetails` works again, and with `vippsActivateCart` restores the cart of a payment the shopper
  cancelled in the app, as the return page already did. Both read the payment through methods that no longer
  existed after the move to the ePayment API, so every call failed.
- The Express cart restore cookie is signed, so it can only restore the cart it was issued for.

### Changed
- **Headless storefronts:** `vippsGetPaymentDetails` and `vippsActivateCart` take a required `token`
  argument. Read it from the `token` parameter of the return URL, next to `reference`, and pass it to both.

## [3.0.4] - 2026-09-30

### Fixed
- Express payments are no longer cancelled while the shopper might still be completing them. The cart
  restore was cancelling the monitoring quote whenever the store was visited after leaving for the
  payment gateway, which on mobile happens by itself when the browser reloads the site during the
  handoff to the app. That took the payment out of the order fetch cron's queue, so no Magento order
  could be created once the shopper approved it. The cart is still restored, but the payment is now
  left for the cron to resolve.
- The cart restore message no longer claims the payment was cancelled when it may still be live, which
  prompted shoppers to start a second payment. It now also appears on the AJAX path taken when the
  browser returns from the app.
- Express shipping options are sent including tax. The excluding-tax figure under-quoted the shopper in
  the app and, since the chosen option is added to the authorised amount, could leave it short of the
  order total and fail placement.
- A payment that never produced an order can now be cancelled, releasing the reserved amount. The
  reference identifying the payment was read from the cart, which has already given it up by then, so
  the request went out without one and was rejected, leaving the monitoring quote in `cancel_failed`.
  It is now read from the monitoring quote, which keeps it for the life of the payment.
- One basket can no longer produce two orders. The check for an existing order looked only under the
  payment's own reference, so a shopper who started Express twice and approved both payments got two
  orders. The surplus payment is now cancelled instead.
- Express orders carry the shopper's own postal code. The fallback for a missing one read a key the API
  does not return, and the tax module's `*` placeholder is truthy, so the placeholder passed through
  onto the order.

### Added
- Payments that 3.0.2 and 3.0.3 cancelled while they were still live are re-checked on upgrade, so an
  order is created where the shopper did pay. Covers the last seven days and skips baskets that already
  produced an order.

## [3.0.3] - 2026-06-15

### Added
- "Show On Virtual Products" setting for the Express button. When disabled (the default), the Express
  shortcut is hidden on product pages for items that don't require shipping (virtual and downloadable),
  since express checkout collects a shipping address (VIPPS-451). Reworked from the community
  contribution in #180.

## [3.0.2] - 2026-06-15

### Fixed
- Prevent the cart from being emptied when returning to the store via the browser back button after
  starting a Vipps/MobilePay Express payment. The pending quote id is stored in a cookie when express
  payment is initiated and the cart is restored from it on return (VIPPS-61).

## [3.0.1] - 2026-06-15

### Added

- New Vipps/MobilePay Express button styling for the ePayment flow, with localized button images
  (Vipps EN/NO/SE, MobilePay DK/EN/FI) and matching translations (VIPPS-38).

## [3.0.0] - 2026-04-03

### Added

- Added support for PHP 8.4
- Added new ePayment shipping option display in Vipps Express
- Added payment details message for ePayment
- Added transaction detail capturing for aggregate values
- Updated profiling for ePayment and added Get Payment Details profiler
- Vipps payment in Klarna checkout flow support

### Changed

- Vipps now uses the ePayment API for express and payment method flows

[3.1.0]: https://github.com/vippsas/vipps-magento/compare/3.0.4...3.1.0
[3.0.4]: https://github.com/vippsas/vipps-magento/compare/3.0.3...3.0.4
[3.0.3]: https://github.com/vippsas/vipps-magento/compare/3.0.2...3.0.3
[3.0.2]: https://github.com/vippsas/vipps-magento/compare/3.0.1...3.0.2
[3.0.1]: https://github.com/vippsas/vipps-magento/compare/3.0.0...3.0.1
