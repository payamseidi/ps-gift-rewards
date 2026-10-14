<?php
/** Isolated Store API/cart smoke tests. Real WordPress E2E validation remains necessary. */
namespace Automattic\WooCommerce\Blocks\Integrations {
    interface IntegrationInterface {
        public function get_name(); public function initialize(); public function get_script_handles();
        public function get_editor_script_handles(); public function get_script_data();
    }
}
namespace Automattic\WooCommerce\StoreApi\Schemas\V1 {
    class CartSchema { const IDENTIFIER = 'cart'; }
}
namespace Automattic\WooCommerce\StoreApi\Exceptions {
    class RouteException extends \Exception {
        public function __construct($code, $message, $status = 400) { parent::__construct($message, $status); }
    }
}
namespace {
    define('ABSPATH', __DIR__);
    define('ARRAY_A', 'ARRAY_A');
    define('PSGR_VERSION', '0.4.0-alpha.1');
    define('PSGR_URL', 'https://example.invalid/psgr/');
    $GLOBALS['options'] = [];
    $GLOBALS['hooks'] = [];
    $GLOBALS['wc_objects'] = [];
    $GLOBALS['assertions'] = 0;
    function check($ok, $description) {
        $GLOBALS['assertions']++;
        if (!$ok) { fwrite(STDERR, 'FAIL: '. $description . "\n"); exit(1); }
    }
    function add_action($hook, $callback, $priority = 10, $arguments = 1) { $GLOBALS['hooks'][$hook][] = $callback; }
    function add_filter($hook, $callback, $priority = 10, $arguments = 1) { add_action($hook, $callback); }
    function add_shortcode($n, $cb) {}
    function get_option($name, $default = false) { return $GLOBALS['options'][$name] ?? $default; }
    function update_option($name, $v, $autoload = null) { $GLOBALS['options'][$name] = $v; return true; }
    function sanitize_key($v) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $v)); }
    function sanitize_text_field($v) { return strip_tags((string) $v); }
    function absint($v) { return abs((int) $v); }
    function is_user_logged_in() { return false; }
    function is_admin() { return false; }
    function wp_doing_ajax() { return false; }
    function wp_date($pattern) { return '2026-10-14'; }
    function get_locale() { return 'en_GB'; }
    function __( $message, $domain = null ) { return $message; }
    function is_wp_error($value) { return $value instanceof WP_Error; }
    function wp_strip_all_tags($value) { return strip_tags($value); }
    function wc_price($v) { return '€'.number_format((float) $v, 2); }
    function wc_get_product($id) { return $GLOBALS['wc_objects'][$id] ?? null; }
    function get_post_status($id) { return 'publish'; }
    function wp_get_attachment_image_url($id, $size) { return 'https://example.invalid/product.png'; }
    function woocommerce_store_api_register_endpoint_data($args) { $GLOBALS['endpoint'] = $args; }
    function woocommerce_store_api_register_update_callback($args) { $GLOBALS['updater'] = $args; }
    class WP_Error {
        private $code; private $message;
        public function __construct($code, $message) { $this->code = $code; $this->message = $message; }
        public function get_error_code() { return $this->code; }
        public function get_error_message() { return $this->message; }
    }
    class WC_Product {
        private $id; private $price; private $stock;
        public function __construct($id, $price, $stock = 50) { $this->id = $id; $this->price = $price; $this->stock = $stock; }
        public function get_name() { return 'Gift '.$this->id; }
        public function get_image_id() { return 12; }
        public function get_price() { return $this->price; }
        public function set_price($p) { $this->price = $p; }
        public function get_status() { return 'publish'; }
        public function get_parent_id() { return 0; }
        public function is_type($type) { return $type === 'simple'; }
        public function is_purchasable() { return true; }
        public function is_in_stock() { return $this->stock > 0; }
        public function get_variation_attributes() { return []; }
    }
    class Mock_Customer {
        public $country = 'DE';
        public function get_shipping_country() { return $this->country; }
    }
    class Mock_Cart {
        public $cart_contents = [];
        public $coupon_codes = [];
        public $number = 0;
        public function get_cart() { return $this->cart_contents; }
        public function get_applied_coupons() { return $this->coupon_codes; }
        public function needs_shipping() { return true; }
        public function remove_cart_item($key) { unset($this->cart_contents[$key]); return true; }
        public function add_to_cart($pid, $qty, $variation, $attrs, $extra) {
            $id = $variation ?: $pid;
            if (!isset($GLOBALS['wc_objects'][$id])) { return false; }
            $this->number++;
            $key = 'item'.$this->number;
            $this->cart_contents[$key] = array_merge([
                'product_id' => $pid, 'variation_id' => $variation, 'quantity' => $qty,
                'line_total' => 0, 'data' => clone $GLOBALS['wc_objects'][$id]
            ], $extra);
            return $key;
        }
        public function calculate_totals() {
            $plugin = PSGR_Plugin::instance();
            $plugin->reconcile($this);
            foreach ($this->cart_contents as &$line) {
                $line['line_total'] = $line['data']->get_price() * $line['quantity'];
            }
            unset($line);
            $plugin->post_totals($this);
        }
    }
    class Mock_Woo {
        public $customer; public $cart;
        public function __construct() { $this->customer = new Mock_Customer(); $this->cart = new Mock_Cart(); }
    }
    function WC() { return $GLOBALS['woocommerce']; }
    require_once __DIR__.'/../payamseidi-ps-gift-rewards/includes/class-psgr-localization.php';
    require_once __DIR__.'/../payamseidi-ps-gift-rewards/includes/class-psgr-rules.php';
    require_once __DIR__.'/../payamseidi-ps-gift-rewards/includes/class-psgr-campaigns.php';
    require_once __DIR__.'/../payamseidi-ps-gift-rewards/includes/class-psgr.php';
    require_once __DIR__.'/../payamseidi-ps-gift-rewards/includes/class-psgr-blocks.php';
    $GLOBALS['woocommerce'] = new Mock_Woo();
    $GLOBALS['wc_objects'][12] = new WC_Product(12, 9.5);
    $GLOBALS['wc_objects'][23] = new WC_Product(23, 8);
    $GLOBALS['wc_objects'][900] = new WC_Product(900, 80);
    $GLOBALS['options'][PSGR_Campaigns::OPTION] = [array_merge(PSGR_Campaigns::defaults(), [
        'status' => 'active', 'gift_ids' => [12, 23], 'excluded_countries' => ['IT']
    ])];
    WC()->cart->add_to_cart(900, 1, 0, [], []);
    WC()->cart->calculate_totals();
    PSGR_Blocks::boot();
    check(isset($GLOBALS['endpoint']) && $GLOBALS['endpoint']['namespace'] === 'ps-gift-rewards', 'cart endpoint registered');
    check(isset($GLOBALS['updater']) && $GLOBALS['updater']['namespace'] === 'ps-gift-rewards', 'Store API update registered');
    check(count(PSGR_Blocks::schema()) === 9, 'schema declares all data fields');
    check(isset(PSGR_Blocks::schema()['strings']), 'localized string schema exposed');
    $data = PSGR_Blocks::data();
    check($data['eligible'] && count($data['gifts']) === 2, 'eligible gifts visible');
    check($data['strings']['free'] === 'FREE', 'localized strings sent through Store API');
    check($data['selected_id'] === 0, 'initially no gift');
    PSGR_Blocks::update(['action' => 'choose', 'id' => 12]);
    $payload = PSGR_Blocks::data();
    check($payload['selected_id'] === 12, 'gift selected via Store API');
    check(count(WC()->cart->get_cart()) === 2, 'two cart lines');
    $lines = WC()->cart->get_cart();
    check($lines['item1']['data']->get_price() === 80, 'purchased product retains price');
    check($lines['item2']['data']->get_price() === 0, 'gift line is free');
    PSGR_Blocks::update(['action' => 'choose', 'id' => 23]);
    check(count(WC()->cart->get_cart()) === 2, 'gift changed, old gift removed');
    check(PSGR_Blocks::data()['selected_id'] === 23, 'replacement selected');
    $rejected = false;
    try { PSGR_Blocks::update(['action' => 'choose', 'id' => 900]); } catch (\Exception $e) { $rejected = true; }
    check($rejected, 'non-gift product rejected');
    check(PSGR_Blocks::data()['selected_id'] === 23, 'failure preserves selected gift');
    WC()->customer->country = 'IT';
    WC()->cart->calculate_totals();
    check(PSGR_Blocks::data()['visible'] === false, 'Italy excluded');
    check(count(WC()->cart->get_cart()) === 1, 'gift removed when destination changes');
    WC()->customer->country = 'DE';
    PSGR_Blocks::update(['action' => 'choose', 'id' => 12]);
    PSGR_Blocks::update(['action' => 'remove', 'id' => 0]);
    check(PSGR_Blocks::data()['selected_id'] === 0, 'remove selection');
    WC()->cart->cart_contents['item1']['data']->set_price(50);
    WC()->cart->calculate_totals();
    $progress = PSGR_Blocks::data();
    check($progress['visible'] && !$progress['eligible'], 'below minimum, progress shown');
    check($progress['remaining'] === '€19.00', 'remaining threshold');
    echo 'PASS: '.$GLOBALS['assertions']." isolated Store API/cart assertions\n";
}
