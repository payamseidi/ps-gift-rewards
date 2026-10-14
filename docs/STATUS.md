# PS Gift Rewards — Development Status

Version: 0.4.0-alpha.1 | Date: 2026-10-14 | Stage: Checkout reliability / catalog gifts / EN–IT UX | Release: Staging only

## Completed
- Existing multi-campaign rules: schedules, priority, country filters, coupon exclusion, after-discount product total threshold.
- WooCommerce native product and variation search in campaign editor; thumbnails of selected products.
- Editable name_en, name_it, message_en, message_it, sanitized and persisted on each campaign.
- Polylang-aware language detection and product ID mapping via pll_get_post when available.
- Localized Classic storefront and Store API strings payload for Blocks.
- Gift replacement preserves old gift if adding a new gift fails.
- Shared checkout validation for Classic and Store API (PSGR-401 invalid reward, PSGR-402 multiple rewards).
- 17-assertion mock smoke suite covering gift swap, tamper validation and EN/IT.
- GitHub CI configured for PHP/JS lint, existing tests, 0.4 smoke and ZIP packaging.

## In progress and backlog
- Full WooCommerce and Blocks end-to-end testing, real checkout/cart lifecycle.
- PS Tools Core standard admin shell: only psgr_admin_parent_slug extension filter is implemented.
- Claims, reports, refunds, gift costs, audit log, event contracts and Messaging Connect integration.
- Customer limits, multi-currency, gift stacking, deeper Polylang for WooCommerce compatibility.
- Product search dropdown thumbnail rendering (currently thumbnails for selected gift products only).

## Blockers / known limitations
- NOT production-ready: no live end-to-end WordPress/WooCommerce tests.
- Blocks UX uses ExperimentalOrderMeta SlotFill; placement needs verification against installed WooCommerce.
- Selecting a gift does not reserve stock; checkout still must perform stock validation.
- Threshold uses after-discount non-gift line_total, excluding shipping and tax. Store currency only.
- Missing product translation falls back to the original product ID.

## Dependencies and contracts
- WordPress 6.2+, WooCommerce, PHP 7.4+; Polylang and PS Tools Core optional.
- WP option: psgr_campaigns_v2; legacy psgr_campaign migration retained.
- Cart keys: _psgr_reward, _psgr_gift_id, _psgr_original_price, _psgr_campaign.
- Order line keys: _psgr_reward, _psgr_campaign, _psgr_original_price.
- Store API wc/store/cart extensions ps-gift-rewards; new strings map.
- Checkout hook: woocommerce_store_api_cart_errors; administrative filter: psgr_admin_parent_slug.
- Capability: manage_woocommerce; no new PS-wide events in this stage.

## Release gates
1. PHP / JavaScript lint, rules, Blocks and 0.4 mock smoke suite.
2. Real staging orders for Classic and Blocks, guest/registered, coupons and country changes.
3. Validate stock, quantity tampering, refunds, translation and Cart/Checkout Block placement.
4. Production release only after integration tests pass.

Next: 0.5.0-alpha — regression matrix, persisted claim lifecycle, PS Tools Core UI integration.
