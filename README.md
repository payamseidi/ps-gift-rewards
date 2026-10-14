# PS Gift Rewards

WooCommerce promotional gift campaign engine by **Payam Seidi** (PS Tools family).

**Latest source:** `0.2.0-alpha.1` — staging only.

The WordPress plugin is in `payamseidi-ps-gift-rewards/`. The gift campaign is configurable; AmicEarth is a use case, not a built-in brand or site restriction.

## Scope implemented

- Create and edit multiple campaigns with scheduling, activation and priority
- Legacy single-campaign option migration to versioned data
- Country inclusion/exclusion, purchase threshold after coupon discounts, excluded coupons
- Server-side gift eligibility, product/variation limit, cart cleanup and checkout validation
- Classic WooCommerce Cart and Checkout gift chooser
- Order line metadata and optional `[ps_gift_rewards]` shortcode

## Requirements and risks

WordPress 6.2+, PHP 7.4+, WooCommerce. **Cart and Checkout Blocks are currently incompatible.** There is no live WooCommerce E2E verification, so use a staging environment only.

## Tests and package

```bash
find payamseidi-ps-gift-rewards -name '*.php' -print0 | xargs -0 -n 1 php -l
php tests/rules-smoke.php
zip -r ps-gift-rewards-0.2.0-alpha.1.zip payamseidi-ps-gift-rewards
```

GitHub Actions builds an installation ZIP artifact when CI passes.

See [Development Status](docs/STATUS.md) for completed features, known limitations, dependencies, data contracts and roadmap.
