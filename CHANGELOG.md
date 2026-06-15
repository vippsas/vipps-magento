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
