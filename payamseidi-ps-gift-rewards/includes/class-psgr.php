<?php
/** WooCommerce integration and Campaigns administration. */
defined('ABSPATH') || exit;
final class PSGR_Plugin {
    private static $instance;
    const MARK = '_psgr_reward';
    public static function instance() { return self::$instance ?: (self::$instance = new self()); }
    private function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_post_psgr_save', [$this, 'save']);
        add_action('admin_post_psgr_create', [$this, 'create']);
        add_action('admin_post_psgr_delete', [$this, 'delete']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('woocommerce_before_cart_totals', [$this, 'selector']);
        add_action('woocommerce_review_order_before_payment', [$this, 'selector']);
        add_shortcode('ps_gift_rewards', [$this, 'render']);
        add_action('wp_ajax_psgr_choose', [$this, 'choose']);
        add_action('wp_ajax_nopriv_psgr_choose', [$this, 'choose']);
        add_action('woocommerce_before_calculate_totals', [$this, 'reconcile'], 9999);
        add_action('woocommerce_after_calculate_totals', [$this, 'post_totals'], 9999);
        add_action('woocommerce_check_cart_items', [$this, 'validate']);
        add_action('woocommerce_checkout_process', [$this, 'validate']);
        add_action('woocommerce_store_api_cart_errors', [$this, 'store_validate'], 10, 2);
        add_filter('woocommerce_get_item_data', [$this, 'item_data'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'order_meta'], 10, 4);
        add_filter('woocommerce_cart_item_quantity', [$this, 'lock_quantity'], 10, 3);
    }
    private function context($cart): array {
        $country = WC()->customer ? strtoupper((string) WC()->customer->get_shipping_country()) : '';
        $total = 0.0;
        foreach ($cart->get_cart() as $line) {
            if (empty($line[self::MARK])) { $total += (float) ($line['line_total'] ?? 0); }
        }
        return [
            'shipping_country' => $country,
            'cart_subtotal' => $total,
            'coupon_codes' => array_map('strtolower', (array) $cart->get_applied_coupons()),
            'customer_logged_in' => is_user_logged_in(),
            'shipping_required' => $cart->needs_shipping(),
            'local_date' => wp_date('Y-m-d'),
        ];
    }
    private function campaign($cart, bool $progress = false): ?array {
        $ctx = $this->context($cart);
        $active = PSGR_Campaigns::selected($ctx);
        if ($active || !$progress) { return $active; }
        // Progress only for customers otherwise eligible, not for excluded countries or coupons.
        $ctx['cart_subtotal'] = PHP_FLOAT_MAX;
        return PSGR_Campaigns::selected($ctx);
    }
    private function gifts(array $campaign): array {
        $products = [];
        foreach ($campaign['gift_ids'] as $id) {
            if (function_exists('pll_get_post')) {
                $translated = pll_get_post($id, PSGR_Localization::language());
                if (is_numeric($translated) && (int) $translated > 0) { $id = (int) $translated; }
            }
            $p = wc_get_product($id);
            if (!$p || !$p->is_purchasable() || !$p->is_in_stock() || !$p->is_type('simple') && !$p->is_type('variation')) { continue; }
            if ($p->get_status() !== 'publish' && !$p->is_type('variation')) { continue; }
            if ($p->is_type('variation') && get_post_status($p->get_parent_id()) !== 'publish') { continue; }
            if ($p->get_price() === '' || (float) $p->get_price() > (float) $campaign['max_value']) { continue; }
            $products[$id] = $p;
        }
        return $products;
    }
    private function actual_id(array $line): int {
        return !empty($line['variation_id']) ? absint($line['variation_id']) : absint($line['product_id'] ?? 0);
    }
    private function valid_gift(array $line, ?array $campaign, array $allowed): bool {
        if (!$campaign || (int) ($line['quantity'] ?? 0) !== 1) { return false; }
        $id = absint($line['_psgr_gift_id'] ?? 0);
        return $id > 0 && $id === $this->actual_id($line) &&
            ($line['_psgr_campaign'] ?? '') === $campaign['id'] && isset($allowed[$id]);
    }
    public function admin_assets($hook): void {
        if (isset($_GET['page']) && sanitize_key(wp_unslash($_GET['page'])) === 'ps-gift-rewards') {
            wp_enqueue_script('wc-enhanced-select');
            wp_enqueue_style('woocommerce_admin_styles');
        }
    }
    public function menu(): void {
        // PS Tools Core integration will use Core's verified menu registrar in a future build.
        $parent = apply_filters('psgr_admin_parent_slug', 'woocommerce');
        add_submenu_page(sanitize_key($parent), 'PS Gift Rewards', 'Gift Rewards', 'manage_woocommerce', 'ps-gift-rewards', [$this, 'admin']);
    }
    private function admin_url(string $id = ''): string {
        $args = ['page' => 'ps-gift-rewards'];
        if ($id !== '') { $args['campaign'] = $id; }
        return add_query_arg($args, admin_url('admin.php'));
    }
    public function admin(): void {
        if (!current_user_can('manage_woocommerce')) { wp_die('Access denied', '', ['response' => 403]); }
        $all = PSGR_Campaigns::all();
        $id = isset($_GET['campaign']) ? sanitize_key(wp_unslash($_GET['campaign'])) : ($all[0]['id'] ?? 'default');
        $campaign = PSGR_Campaigns::get($id);
        echo '<div class="wrap"><h1>PS Gift Rewards <small style="font-size:13px">0.4.0-alpha.1 · Payam Seidi</small></h1>';
        echo '<p>Campaigns &amp; Rule Engine · Classic and Block Cart/Checkout alpha</p>';
        if (isset($_GET['saved'])) { echo '<div class="notice notice-success"><p>Campaign saved.</p></div>'; }
        echo '<h2>Campaigns</h2><table class="widefat striped"><thead><tr><th>Name</th><th>Status</th><th>Priority</th><th>Threshold</th><th></th></tr></thead><tbody>';
        foreach ($all as $item) {
            echo '<tr><td>'.esc_html($item['name']).'</td><td>'.esc_html($item['status']).'</td><td>'.esc_html($item['priority']).'</td><td>'.wp_kses_post(wc_price($item['min_total'])).'</td><td><a href="'.esc_url($this->admin_url($item['id'])).'">Edit</a></td></tr>';
        }
        echo '</tbody></table><p><form method="post" style="display:inline" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="psgr_create">';
        wp_nonce_field('psgr_create'); submit_button('New Campaign', 'secondary', 'submit', false); echo '</form></p>';
        if (!$campaign) { echo '</div>'; return; }
        echo '<hr><h2>Edit: '.esc_html($campaign['name']).'</h2>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="psgr_save"><input type="hidden" name="campaign_id" value="'.esc_attr($campaign['id']).'">';
        wp_nonce_field('psgr_save');
        $rows = [
            ['name', 'Internal Campaign Name', 'text'],
            ['name_en', 'English customer title', 'text'], ['name_it', 'Italian customer title', 'text'],
            ['message_en', 'English eligible message', 'text'], ['message_it', 'Italian eligible message', 'text'],
            ['status', 'Status', 'status'], ['priority', 'Priority (higher wins)', 'number'],
            ['min_total', 'Minimum products total after discounts, excluding shipping/tax', 'number'],
            ['max_value', 'Maximum gift sale value', 'number'],
            ['included_countries', 'Only countries (blank means all available)', 'csv'],
            ['excluded_countries', 'Excluded destination countries (ISO, e.g. IT)', 'csv'],
            ['excluded_coupons', 'Excluded coupons (comma separated)', 'csv'],
            ['gift_ids', 'Gift products and variations', 'products'],
            ['starts_on', 'Start date (site time)', 'date'], ['ends_on', 'End date (site time)', 'date'],
            ['show_progress', 'Display unlock progress', 'checkbox'],
        ];
        echo '<table class="form-table" role="presentation">';
        foreach ($rows as [$key, $label, $type]) {
            $value = $campaign[$key];
            echo '<tr><th><label for="psgr_'.esc_attr($key).'">'.esc_html($label).'</label></th><td>';
            if ($type === 'checkbox') {
                echo '<input type="checkbox" id="psgr_'.esc_attr($key).'" name="psgr['.esc_attr($key).']" value="1" '.checked((bool) $value, true, false).'>';
            } elseif ($type === 'products') {
                echo '<select id="psgr_gift_ids" class="wc-product-search" style="width:440px;max-width:100%" name="psgr[gift_ids][]" multiple="multiple" data-action="woocommerce_json_search_products_and_variations" data-placeholder="Search products and variations...">';
                foreach ((array) $value as $product_id) {
                    $product = wc_get_product(absint($product_id));
                    if ($product) { echo '<option value="'.esc_attr($product_id).'" selected>'.esc_html($product->get_formatted_name()).'</option>'; }
                }
                echo '</select><p class="description">Select products from your WooCommerce catalog. Product selection does not reserve stock.</p>';
            } elseif ($type === 'status') {
                echo '<select id="psgr_status" name="psgr[status]">';
                foreach (['draft', 'paused', 'active'] as $status) { echo '<option value="'.esc_attr($status).'" '.selected($value, $status, false).'>'.esc_html(ucfirst($status)).'</option>'; }
                echo '</select>';
            } else {
                $display = is_array($value) ? implode(',', $value) : $value;
                echo '<input class="regular-text" type="'.esc_attr($type === 'csv' ? 'text' : $type).'" id="psgr_'.esc_attr($key).'" name="psgr['.esc_attr($key).']" value="'.esc_attr($display).'" '.($type === 'number' ? 'min="0" step="0.01"' : '').'>';
            }
            echo '</td></tr>';
        }
        echo '</table>'; submit_button('Save Campaign'); echo '</form>';
        if ($campaign['id'] !== 'default') {
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm(\'Delete this campaign?\')"><input type="hidden" name="action" value="psgr_delete"><input type="hidden" name="campaign_id" value="'.esc_attr($campaign['id']).'">';
            wp_nonce_field('psgr_delete'); submit_button('Delete Campaign', 'delete', 'submit', false); echo '</form>';
        }
        echo '<p>Rules within a campaign are combined with AND; matching campaigns are resolved by priority. No rewards stacking in this alpha. Shortcode: <code>[ps_gift_rewards]</code>.</p></div>';
    }
    public function save(): void {
        if (!current_user_can('manage_woocommerce')) { wp_die('Forbidden', '', ['response' => 403]); }
        check_admin_referer('psgr_save');
        $id = isset($_POST['campaign_id']) ? sanitize_key(wp_unslash($_POST['campaign_id'])) : '';
        $existing = PSGR_Campaigns::get($id);
        if (!$existing) { wp_die('Campaign not found', '', ['response' => 404]); }
        $p = isset($_POST['psgr']) && is_array($_POST['psgr']) ? wp_unslash($_POST['psgr']) : [];
        foreach (['name', 'name_en', 'name_it', 'message_en', 'message_it', 'status', 'priority', 'min_total', 'max_value', 'included_countries', 'excluded_countries', 'excluded_coupons', 'starts_on', 'ends_on'] as $field) {
            $existing[$field] = isset($p[$field]) && is_scalar($p[$field]) ? $p[$field] : '';
        }
        $existing['gift_ids'] = isset($p['gift_ids']) && is_array($p['gift_ids']) ? $p['gift_ids'] : [];
        $existing['show_progress'] = !empty($p['show_progress']);
        PSGR_Campaigns::put($existing);
        wp_safe_redirect(add_query_arg('saved', '1', $this->admin_url($id))); exit;
    }
    public function create(): void {
        if (!current_user_can('manage_woocommerce')) { wp_die('Forbidden', '', ['response' => 403]); }
        check_admin_referer('psgr_create');
        $new = PSGR_Campaigns::defaults();
        $new['id'] = 'campaign_'.str_replace('-', '', wp_generate_uuid4());
        $new['name'] = 'New Gift Campaign';
        PSGR_Campaigns::put($new);
        wp_safe_redirect($this->admin_url($new['id'])); exit;
    }
    public function delete(): void {
        if (!current_user_can('manage_woocommerce')) { wp_die('Forbidden', '', ['response' => 403]); }
        check_admin_referer('psgr_delete');
        PSGR_Campaigns::remove(sanitize_key(wp_unslash($_POST['campaign_id'] ?? '')));
        wp_safe_redirect($this->admin_url()); exit;
    }
    public function assets(): void {
        $post = get_post();
        if ($post && (has_block('woocommerce/cart', $post) || has_block('woocommerce/checkout', $post))) { return; }
        if (!is_cart() && !is_checkout() && !($post && has_shortcode((string) $post->post_content, 'ps_gift_rewards'))) { return; }
        wp_enqueue_style('psgr', PSGR_URL.'assets/gifts.css', [], PSGR_VERSION);
        wp_enqueue_script('psgr', PSGR_URL.'assets/gifts.js', ['jquery'], PSGR_VERSION, true);
        wp_localize_script('psgr', 'PSGR', [
            'ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('psgr_choose'),
            'error' => PSGR_Localization::text('error'),
        ]);
    }
    public function selector(): void { echo $this->render(); }
    public function render(): string {
        if (!function_exists('WC') || !WC()->cart) { return ''; }
        $cart = WC()->cart;
        $campaign = $this->campaign($cart, true);
        if (!$campaign) { return ''; }
        $eligible = PSGR_Rules::campaign_matches($campaign, $this->context($cart));
        if (!$eligible && !$campaign['show_progress']) { return ''; }
        ob_start();
        echo '<section class="psgr-panel" aria-label="'.esc_attr(PSGR_Localization::text('gift_rewards')).'"><h3>'.esc_html(PSGR_Localization::campaign_title($campaign)).'</h3>';
        if (!$eligible) {
            $remaining = max(0, (float) $campaign['min_total'] - $this->context($cart)['cart_subtotal']);
            echo '<p>'.esc_html(sprintf(PSGR_Localization::text('unlock_prefix'), wp_strip_all_tags(wc_price($remaining)))).'</p>';
        } else {
            $selected = $this->selected($cart);
            echo '<p>'.esc_html(PSGR_Localization::campaign_message($campaign)).'</p><div class="psgr-grid">';
            $products = $this->gifts($campaign);
            foreach ($products as $id => $product) {
                echo '<div class="psgr-card">'.wp_kses_post($product->get_image('woocommerce_thumbnail')).'<strong>'.esc_html($product->get_name()).'</strong><span>'.wp_kses_post(wc_price($product->get_price())).' · <b>'.esc_html(PSGR_Localization::text('free')).'</b></span>';
                echo '<button type="button" class="button psgr-choice" data-id="'.esc_attr($id).'" '.disabled($selected === $id, true, false).'>'.esc_html($selected === $id ? PSGR_Localization::text('selected') : PSGR_Localization::text('choose')).'</button></div>';
            }
            echo '</div>';
            if (!$products) { echo '<p>'.esc_html(PSGR_Localization::text('no_gifts')).'</p>'; }
            if ($selected) { echo '<button type="button" class="psgr-choice psgr-remove" data-id="0">'.esc_html(PSGR_Localization::text('remove')).'</button>'; }
        }
        echo '<p class="psgr-status" role="status" aria-live="polite"></p></section>';
        return ob_get_clean();
    }
    private function selected($cart): int {
        foreach ($cart->get_cart() as $line) {
            if (!empty($line[self::MARK])) { return absint($line['_psgr_gift_id'] ?? 0); }
        }
        return 0;
    }
    /** Shared, server-authoritative selection for Classic AJAX and the Store API. */
    public function select_gift(int $id) {
        if (!function_exists('WC') || !WC()->cart) {
            return new WP_Error('PSGR-303', __('Your cart is not available.', 'payamseidi-ps-gift-rewards'));
        }
        $cart = WC()->cart;
        $campaign = $this->campaign($cart);
        $allowed = $campaign ? $this->gifts($campaign) : [];
        if ($id && (!$campaign || !isset($allowed[$id]))) {
            return new WP_Error('PSGR-304', __('This gift is not available for your order.', 'payamseidi-ps-gift-rewards'));
        }
        if ($id && !PSGR_Rules::campaign_matches($campaign, $this->context($cart))) {
            return new WP_Error('PSGR-305', __('Your order no longer qualifies for this gift.', 'payamseidi-ps-gift-rewards'));
        }
        if ($id && $this->selected($cart) === $id) { return true; }
        $old_keys = [];
        foreach ($cart->get_cart() as $key => $line) {
            if (!empty($line[self::MARK])) { $old_keys[] = $key; }
        }
        $new_key = '';
        if ($id) {
            $p = $allowed[$id];
            $is_variation = $p->is_type('variation');
            $added = $cart->add_to_cart(
                $is_variation ? $p->get_parent_id() : $id,
                1,
                $is_variation ? $id : 0,
                $is_variation ? $p->get_variation_attributes() : [],
                [
                    self::MARK => 1,
                    '_psgr_gift_id' => $id,
                    '_psgr_original_price' => (string) $p->get_price(),
                    '_psgr_campaign' => $campaign['id'],
                ]
            );
            if (!$added) {
                return new WP_Error('PSGR-306', __('We could not add this gift. Please choose another.', 'payamseidi-ps-gift-rewards'));
            }
            $new_key = $added;
        }
        // Preserve the existing gift if adding the replacement fails.
        foreach ($old_keys as $key) {
            if ($key !== $new_key) { $cart->remove_cart_item($key); }
        }
        $cart->calculate_totals();
        return true;
    }
    public function choose(): void {
        check_ajax_referer('psgr_choose', 'nonce');
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $result = $this->select_gift($id);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message(), 'code' => $result->get_error_code()], 422);
        }
        wp_send_json_success(['message' => $id ? 'Gift selected.' : 'Gift removed.']);
    }
    /** Data exposed through wc/store/cart/extensions for Cart & Checkout Blocks. */
    public function store_payload(): array {
        $empty = [
            'visible' => false, 'eligible' => false, 'campaign_id' => '',
            'title' => '', 'message' => '', 'remaining' => '', 'selected_id' => 0, 'gifts' => [],
        ];
        if (!function_exists('WC') || !WC()->cart) { return $empty; }
        $cart = WC()->cart;
        $campaign = $this->campaign($cart, true);
        if (!$campaign) { return $empty; }
        $context = $this->context($cart);
        $eligible = PSGR_Rules::campaign_matches($campaign, $context);
        if (!$eligible && !$campaign['show_progress']) { return $empty; }
        $data = $empty;
        $data['visible'] = true;
        $data['eligible'] = (bool) $eligible;
        $data['campaign_id'] = $campaign['id'];
        $data['title'] = PSGR_Localization::campaign_title($campaign);
        $data['strings'] = PSGR_Localization::strings();
        if ($eligible) {
            $data['message'] = PSGR_Localization::campaign_message($campaign);
            $data['selected_id'] = $this->selected($cart);
            foreach ($this->gifts($campaign) as $id => $p) {
                $image_id = (int) $p->get_image_id();
                if (!$image_id && $p->is_type('variation')) {
                    $parent = wc_get_product($p->get_parent_id());
                    $image_id = $parent ? (int) $parent->get_image_id() : 0;
                }
                $data['gifts'][] = [
                    'id' => (int) $id,
                    'name' => $p->get_name(),
                    'price' => wp_strip_all_tags(wc_price($p->get_price())),
                    'image' => $image_id ? (string) wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') : '',
                ];
            }
        } else {
            $remaining = max(0, (float) $campaign['min_total'] - $context['cart_subtotal']);
            $data['message'] = PSGR_Localization::text('unlock_intro');
            $data['remaining'] = wp_strip_all_tags(wc_price($remaining));
        }
        return $data;
    }
    private function cleanup($cart, ?array $campaign, bool $force_zero = false): bool {
        $allowed = $campaign ? $this->gifts($campaign) : [];
        $first = false;
        $changed = false;
        foreach ($cart->get_cart() as $key => $line) {
            if (empty($line[self::MARK])) { continue; }
            if ($first || !$this->valid_gift($line, $campaign, $allowed)) {
                $cart->remove_cart_item($key);
                $changed = true;
                continue;
            }
            $first = true;
            if ($force_zero && isset($line['data']) && $line['data'] instanceof WC_Product) {
                // Clone to avoid a shared WC_Product instance setting a paid line to zero.
                $gift_product = clone $line['data'];
                $gift_product->set_price(0);
                $cart->cart_contents[$key]['data'] = $gift_product;
            }
        }
        return $changed;
    }
    public function reconcile($cart): void {
        if (is_admin() && !wp_doing_ajax()) { return; }
        $this->cleanup($cart, $this->campaign($cart), true);
    }
    public function post_totals($cart): void {
        static $busy = false;
        if ($busy || (is_admin() && !wp_doing_ajax())) { return; }
        $busy = true;
        $changed = $this->cleanup($cart, $this->campaign($cart));
        if ($changed) { $cart->calculate_totals(); }
        $busy = false;
    }
    public function validate(): void {
        if (!function_exists('WC') || !WC()->cart) { return; }
        $cart = WC()->cart;
        $campaign = $this->campaign($cart);
        $allowed = $campaign ? $this->gifts($campaign) : [];
        $count = 0;
        foreach ($cart->get_cart() as $line) {
            if (empty($line[self::MARK])) { continue; }
            $count++;
            if (!$this->valid_gift($line, $campaign, $allowed)) {
                wc_add_notice(__('Your gift is no longer eligible. Please review your cart.', 'payamseidi-ps-gift-rewards'), 'error'); return;
            }
        }
        if ($count > 1) { wc_add_notice(__('Only one gift can be selected.', 'payamseidi-ps-gift-rewards'), 'error'); }
    }
    public function item_data($data, $line) {
        if (!empty($line[self::MARK])) { $data[] = ['key' => PSGR_Localization::text('reward'), 'value' => PSGR_Localization::text('complimentary')]; }
        return $data;
    }
    public function order_meta($item, $key, $values, $order): void {
        if (empty($values[self::MARK])) { return; }
        $item->add_meta_data('_psgr_reward', 1, true);
        $item->add_meta_data('_psgr_campaign', sanitize_key($values['_psgr_campaign'] ?? ''), true);
        $item->add_meta_data('_psgr_original_price', wc_format_decimal($values['_psgr_original_price'] ?? 0), true);
        $item->add_meta_data('Reward', 'Complimentary gift', true);
    }
    public function lock_quantity($html, $key, $line) { return !empty($line[self::MARK]) ? '1' : $html; }
}
