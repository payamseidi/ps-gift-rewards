# PS Gift Rewards — Development Status

Version: 0.3.0-alpha.1 | Date: 2026-10-14 | Status: STAGING ALPHA

## Scope
WooCommerce Cart & Checkout Blocks via Store API and unified gift selection.

## Completed
- Multi-campaign selection with country conditions, after-discount threshold, coupons, date windows and priority.
- Classic WooCommerce cart/checkout gift chooser and gift order line metadata.
- Store API wc/store/cart extension under namespace ps-gift-rewards.
- Store API cart/extensions callback supports choose/remove with friendly error codes.
- Cart and Checkout Blocks IntegrationInterface and ExperimentalOrderMeta SlotFill.
- Shared select_gift() with server-side checks for gift pool, country, campaign, quantity and stock.
- 23 standalone rule assertions plus 18 mocked Store API/cart assertions; syntax check and packaging workflow.
- HPOS support declaration, Cart/Checkout Blocks support declaration (not E2E certified).

## Not complete
- Real WordPress/WooCommerce checkout end-to-end validation with guest sessions and changing addresses.
- Test on actual Block Checkout, payment integrations, variations, concurrency and refunds.
- PS Tools Core admin-shell integration (admin currently under WooCommerce), custom permissions and diagnostics.
- Polylang EN/IT mapping, RTL, product selection UI, campaign theming.
- Claims, analytics, cost reporting, event bridge and notifications.
- Multiple gift stacking, richer rule builder, multi-currency accounting policy.

## Risks
- Alpha: staging only, not production certified. Simulated tests cannot establish live compatibility.
- ExperimentalOrderMeta SlotFill may change with future WooCommerce updates.
- Only one gift and top-priority campaign; no stacking.
- Threshold excludes tax, shipping and gift rows, uses non-gift line_total after discounts.
- Gift choice does not reserve stock before normal WooCommerce checkout processing.

## Contracts
- WP options: psgr_campaigns_v2, legacy psgr_campaign retained.
- Cart data: _psgr_reward, _psgr_gift_id, _psgr_campaign, _psgr_original_price.
- Order item metadata: _psgr_reward, _psgr_campaign, _psgr_original_price.
- Store API: wc/store/v1/cart extensions["ps-gift-rewards"] provides visible/eligible/campaign_id/title/message/remaining/selected_id/gifts.
- Store API update: cart/extensions namespace=ps-gift-rewards, data={action:"choose",id:N} or {action:"remove",id:0}.
- Classic AJAX: psgr_choose with nonce; admin capability manage_woocommerce.
- PS Event Contract: not yet added; Core/Messaging/Polylang remain optional future integrations.

## Next stage
0.4.0-alpha: live WooCommerce Blocks testing, product picker, admin UX and localization preparation.
