# PS Gift Rewards for WooCommerce

**Author:** Payam Seidi · **Family:** PS Tools · **Version:** 0.3.0-alpha.1

Rule-driven complimentary gift campaigns for WooCommerce. AmicEarth is a configurable initial deployment; no store-specific rules or brand are hard-coded.

## Currently implemented
- Multiple campaigns with country rules, threshold, excluded coupons, priority and date schedules.
- One chosen complimentary product or variation, priced zero only in its cart line.
- Classic Cart/Checkout picker, WooCommerce Cart/Checkout Blocks SlotFill and Store API update.
- Shared server-side validation for guest/customer cart sessions, item availability and eligibility.
- HPOS-compatible order metadata, responsive gift cards, progress messaging.

## Installation and configuration
Install ZIP via WordPress Plugins > Add New > Upload Plugin. Requires WooCommerce, PHP 7.4+ and WordPress 6.2+. Go to WooCommerce > Gift Rewards. Define eligible destinations, gift IDs, threshold, and activate the campaign. Use **staging only** for this alpha.

## Important limitations
No actual WordPress/WooCommerce live checkout E2E tests have been completed. WooCommerce's ExperimentalOrderMeta slot may change. Polylang mapping, reports/claims, PS Tools Core admin integration, currency conversion and simultaneous multiple reward stacking are not implemented.

Checkout must be tested with both Classic and Blocks before any production use.
