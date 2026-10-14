# PS Gift Rewards 0.3 — Staging Acceptance Matrix

Run each storefront test in **Classic** and **Blocks** Cart/Checkout, including a mobile viewport.

- [ ] Germany €50 basket: no selectable gift; progress toward €69.
- [ ] Germany €85: allow one gift up to €10.
- [ ] France €120: allow one gift.
- [ ] Italy €100: no gift.
- [ ] Change shipping country DE -> IT: gift removed and totals recalculate.
- [ ] Decrease eligible product subtotal €85 -> €60: gift removed.
- [ ] Coupon reduces subtotal below threshold: gift removed.
- [ ] Replace one chosen gift: old row removed, exactly one gift remains.
- [ ] Buy same product normally plus as gift: paid row retains original price.
- [ ] Attempt direct Store API selection for unauthorized product: reject with 4xx.
- [ ] Attempt more than one gift/quantity >1: blocked or repaired.
- [ ] Gift goes out of stock before checkout: no invalid free order.
- [ ] Variation-based gift: attributes and price remain correct.
- [ ] WooCommerce checkout with guest and logged-in customers.
- [ ] Shipping address changes immediately reflected in Store API.
- [ ] Payment gateway test purchase completes, gift shown in order and email.
- [ ] HPOS enabled and disabled.
- [ ] Mobile responsive product cards; friendly errors.
- [ ] Compatibility with all active plugins, especially coupon and checkout customizations.

**Release gate:** Production rollout is blocked until applicable cases pass and review is documented.
