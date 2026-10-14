# PS Gift Rewards — Development Status
Date: 2026-10-14
Version: 0.1.0-alpha.1
Stage: Foundation / staging prototype

## Scope
Site-configurable single complimentary gift campaign for WooCommerce Classic Cart and Checkout.

## Completed
- Plugin bootstrap, HPOS compatibility declaration, WooCommerce dependency check
- Campaign admin settings (minimum subtotal, maximum gift value, excluded shipping countries, gift product IDs)
- Server-side eligibility and cart reconciliation
- AJAX gift selection and customer choice UI; responsive assets
- Order-line metadata and basic validations
- Public GitHub repository established: payamseidi/ps-gift-rewards

## Not completed
- WooCommerce Cart and Checkout Blocks / Store API integration
- Automated tests across actual checkout, shipping changes, discounts and refunds
- PS Tools Core API-level integration (currently only optional admin placement)
- Multi-campaign database schema, advanced rule composer, usage limits
- Polylang product mapping, translation administration
- Claims reporting, actual gift costs, analytics, event contracts, permissions/audit subsystem

## Dependencies
Required: WordPress and WooCommerce.
Optional: PS Tools Core (not yet verified); Polylang (planned).

## Data Contract
WP option: psgr_campaign.
Cart item: _psgr_reward, _psgr_gift_id, _psgr_original_price, _psgr_campaign.
Order item: _psgr_reward, _psgr_campaign, _psgr_original_price.
AJAX: wp_ajax_psgr_choose and wp_ajax_nopriv_psgr_choose.

## Release Gate
ALPHA: staging only. PHP lint != functional integration test.
Next: 0.2.0-alpha — campaign/rules model, validation hardening, automated testing setup.
