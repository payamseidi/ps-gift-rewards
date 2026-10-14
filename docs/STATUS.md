# PS Gift Rewards — Project Status

**Version:** 0.2.0-alpha.1 · **Date:** 2026-10-14 · **Status:** Development/Staging

## Stage scope

Generic campaign repository, deterministic rule engine, legacy option migration and hardened WooCommerce Classic integration.

## Completed in this stage

- Versioned `psgr_campaigns_v2` WP option containing ordered campaigns (no custom tables yet).
- Legacy `psgr_campaign` settings migrated without deleting the old settings.
- Multi-campaign list and editor (create/edit/delete/status/priority/scheduling).
- Explicit country inclusion/exclusion, minimum after-discount products total, excluded coupons.
- First eligible campaign selected by priority, one gift max per cart/order.
- PHP-only rule evaluation with fail-closed unsupported operators.
- Validate cart item product/variation ID, campaign identity, quantity, stock and eligibility.
- Variant attribute mapping during add-to-cart; gift line product object cloned before price-zero operation.
- Post-total cleanup for eligibility changes from coupons or cart recalculations.
- HPOS compatibility declaration; Cart/Checkout Blocks declared **incompatible**.
- Automated PHP syntax and standalone rule/migration smoke checks.

## In progress / backlog

- WooCommerce Store API + Cart and Checkout Blocks (next major delivery).
- Actual in-WP integration tests on supported WordPress/WooCommerce versions.
- PS Tools Core admin-shell and permissions integration (current menu under WooCommerce).
- Persistent claims/usage limits, refunds, reporting, actual gift costs and analytics.
- Rule composer for arbitrary AND/OR groups (engine supports groups, editor currently exposes safe preset clauses only).
- Customer segmentation, automatic reward distribution, discounts, currency conversion policy.
- Polylang EN/IT string and product variation mapping.
- Compatibility with existing promotions, checkout customizations and stock reservation at production scale.

## Known limitations / blockers

- Alpha stage: **DO NOT deploy to a live checkout** without staging acceptance tests.
- No Cart/Checkout Blocks integration; explicitly marked incompatible.
- Threshold is the sum of WooCommerce non-gift `line_total` after discounts, excluding tax and shipping. Multi-currency campaigns not yet supported.
- Selection is not a stock reservation. WooCommerce's stock checks still apply at checkout.
- Rule context uses WooCommerce's shipping country; must be revalidated after address updates.
- WordPress/WooCommerce live end-to-end tests have NOT been performed in this environment.

## Dependencies and contracts

- Requires WooCommerce; optional future integrations: PS Tools Core, PS Event Bridge, PS Messaging Connect, Polylang.
- WP options: `psgr_campaigns_v2` (new); `psgr_campaign` (legacy, read once; retained).
- Campaign schema: `id`, `name`, `status`, `priority`, `starts_on`, `ends_on`, `min_total`, `max_value`, `gift_ids`, `included_countries`, `excluded_countries`, `excluded_coupons`, `show_progress`, `rule_groups`.
- Cart line: `_psgr_reward`, `_psgr_gift_id`, `_psgr_original_price`, `_psgr_campaign`.
- Order item meta: `_psgr_reward`, `_psgr_campaign`, `_psgr_original_price`.
- AJAX: `wp_ajax_psgr_choose`, `wp_ajax_nopriv_psgr_choose`; nonce `psgr_choose`.
- API & Event Contract: no public REST endpoints or emitted PS events in this stage.
- Permissions: WooCommerce `manage_woocommerce` for campaign administration; storefront guest checkout supported.

## Next release

`0.3.0-alpha`: Store API/Blocks integration planning, reliable checkout test matrix, user experience refinements.
