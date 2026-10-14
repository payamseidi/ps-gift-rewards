# PS Gift Rewards for WooCommerce

**By Payam Seidi · PS Tools Family · 0.4.0-alpha.1 (staging only)**

Configurable gift campaigns for WooCommerce. AmicEarth is an initial campaign deployment, not a hard-coded plugin brand. Example: all shipping destinations except Italy, €69 merchandise threshold after discounts, customer selects one gift priced up to €10.

## Implemented

- Multiple campaigns, prioritization, country/coupon/date rules and after-discount eligibility
- Classic Cart/Checkout gift UI and an experimental Cart/Checkout Blocks Store API extension
- Separate free cart line, original retail price metadata and gift tampering validation
- WooCommerce admin product search for gifts with thumbnails for **selected** items
- Localized English/Italian titles, messages and gift controls; Polylang product-ID lookup where available
- Safe gift swap: existing gift stays in cart if adding the replacement fails
- Shared server-side checkout validation for Classic and Store API (PSGR-401/402)
- PHP/JS lint and smoke tests, CI-generated plugin ZIP artifact

## Installation

Install the ZIP under **Plugins → Add New → Upload Plugin** in a **staging** WordPress site. Requires WooCommerce, WordPress 6.2+ and PHP 7.4+. Configure under WooCommerce → Gift Rewards. Native product search replaces manual IDs. One gift maximum per cart/order.

## Limitations

**Not production-certified.** No live WordPress/WooCommerce end-to-end checkout run yet. Block UI uses WooCommerce ExperimentalOrderMeta, which may change. Currency conversion, stacking multiple gifts, persisted claims/cost reporting, and full PS Tools Core UI integration remain on the roadmap. Polylang compatibility depends on installed integrations and is not formally certified.

See [docs/STATUS.md](docs/STATUS.md) for release gates, backlog, data contracts, dependencies and known issues.
