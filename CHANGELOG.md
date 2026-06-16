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
