# PS Gift Rewards

WooCommerce gift and reward campaigns by **Payam Seidi** (PS Tools family).

**Version:** 0.3.0-alpha.1 — for staging only. AmicEarth is a configurable use case, not a hard-coded brand.

### Included
Multi-campaign rules, country/threshold/coupon checks, one free gift, server validation, responsive gift chooser, Classic Cart/Checkout, WooCommerce Blocks + Store API, HPOS order line metadata and unlock progress.

### Quality checks
Run PHP lint, Node JavaScript syntax check, php tests/rules-smoke.php and php tests/blocks-smoke.php. GitHub Actions packages the WordPress install ZIP after running checks.

### Limitations
This integration uses the experimental WooCommerce ExperimentalOrderMeta slot. No actual WordPress checkout/payment end-to-end test has been completed. Never use on a live store until staging acceptance passes. Polylang, reports and PS Tools Core admin integration remain to be built.

See docs/STATUS.md and docs/TEST-MATRIX.md.
