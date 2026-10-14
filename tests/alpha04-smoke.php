<?php
/** Standalone smoke: selection, safe swap, tamper check, EN/IT copy. */
define('ABSPATH', __DIR__);
function add_action(...$args) {} function add_filter(...$args) {} function add_shortcode(...$args) {}
function __($x, $domain = '') { return $x; }
function get_locale() { return $GLOBALS['locale']; }
function sanitize_key($x) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $x)); }
function sanitize_text_field($x) { return trim(strip_tags((string) $x)); }
function absint($x) { return abs((int) $x); }
function wp_date($fmt) { return '2026-10-14'; }
function get_option($key, $default = null) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; return true; }
function is_user_logged_in() { return false; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function get_post_status($id) { return 'publish'; }
function wc_price($n) { return 'EUR '.number_format((float) $n, 2); }
function wp_strip_all_tags($x) { return strip_tags($x); }
function wp_get_attachment_image_url() { return ''; }
function get_post() { return null; }
function is_cart() { return false; }
function is_checkout() { return false; }
function esc_html($x) { return htmlspecialchars((string) $x, ENT_QUOTES); }
function esc_attr($x) { return esc_html($x); }
function wp_kses_post($x) { return $x; }
function disabled($x, $y, $echo = false) { return $x === $y ? 'disabled="disabled"' : ''; }
class WP_Error {
    private $code, $message;
    function __construct($code = '', $message = '') { $this->code = $code; $this->message = $message; }
    function get_error_code() { return $this->code; }
    function get_error_message() { return $this->message; }
    function add($code, $message) { $GLOBALS['errors'][] = [$code, $message]; }
}
class WC_Product {
    private $id, $price;
    function __construct($id, $price) { $this->id = $id; $this->price = $price; }
    function is_purchasable() { return true; }
    function is_in_stock() { return true; }
    function is_type($type) { return $type === 'simple'; }
    function get_status() { return 'publish'; }
    function get_price() { return $this->price; }
    function get_name() { return 'Gift '.$this->id; }
    function get_image($size) { return '<img alt="">'; }
    function get_image_id() { return 0; }
    function set_price($price) { $this->price = $price; }
}
class WC_Cart {
    public $cart_contents = [], $add_fails = false;
    function get_cart() { return $this->cart_contents; }
    function get_applied_coupons() { return []; }
    function needs_shipping() { return true; }
    function add_to_cart($id, $qty, $variation, $attrs, $meta) {
        if ($this->add_fails) { return false; }
        $real_id = $variation ?: $id;
        $key = 'gift'.$real_id;
        $this->cart_contents[$key] = array_merge($meta, [
            'product_id' => $id, 'variation_id' => $variation, 'quantity' => $qty,
            'line_total' => 0, 'data' => $GLOBALS['products'][$real_id],
        ]);
        return $key;
    }
    function remove_cart_item($key) { unset($this->cart_contents[$key]); }
    function calculate_totals() {}
}
class WC_Test_Container { public $cart, $customer; }
function WC() { return $GLOBALS['woo']; }
function wc_get_product($id) { return $GLOBALS['products'][$id] ?? false; }
$src = dirname(__DIR__).'/payamseidi-ps-gift-rewards/includes/';
require $src.'class-psgr-localization.php';
require $src.'class-psgr-rules.php';
require $src.'class-psgr-campaigns.php';
require $src.'class-psgr.php';
$GLOBALS['locale'] = 'en_GB';
$GLOBALS['options']['psgr_campaigns_v2'] = [array_merge(PSGR_Campaigns::defaults(), [
    'status'=>'active','gift_ids'=>[101,102], 'min_total'=>69,'max_value'=>10,
    'name_it'=>'Regalo spedizione','message_it'=>'Scegli il regalo'
])];
$GLOBALS['products'] = [101=>new WC_Product(101,9),102=>new WC_Product(102,8)];
$GLOBALS['woo'] = new WC_Test_Container();
WC()->cart = new WC_Cart();
WC()->customer = new class { function get_shipping_country() { return 'DE'; } };
WC()->cart->cart_contents['paid'] = ['product_id'=>55,'variation_id'=>0,'quantity'=>1,'line_total'=>85,'data'=>new WC_Product(55,85)];
function check($truth, $message) {
    $GLOBALS['checks'] = ($GLOBALS['checks'] ?? 0) + 1;
    if (!$truth) { fwrite(STDERR, 'FAIL: '.$message.PHP_EOL); exit(1); }
}
$plugin = PSGR_Plugin::instance();
check($plugin->select_gift(101) === true, 'add gift');
check(isset(WC()->cart->cart_contents['gift101']), 'gift exists');
WC()->cart->add_fails = true;
$failed = $plugin->select_gift(102);
check(is_wp_error($failed) && $failed->get_error_code() === 'PSGR-306','failed swap has code');
check(isset(WC()->cart->cart_contents['gift101']), 'failed swap leaves first gift');
WC()->cart->add_fails = false;
check($plugin->select_gift(102) === true, 'successful swap');
check(isset(WC()->cart->cart_contents['gift102']) && !isset(WC()->cart->cart_contents['gift101']), 'only new gift');
check($plugin->gift_issues(WC()->cart) === [], 'valid single gift');
WC()->cart->cart_contents['gift102']['quantity'] = 2;
check(isset($plugin->gift_issues(WC()->cart)['PSGR-401']), 'tampered quantity is invalid');
WC()->cart->cart_contents['gift102']['quantity'] = 1;
$GLOBALS['locale'] = 'it_IT';
check(PSGR_Localization::language() === 'it', 'Italian language');
check(PSGR_Localization::campaign_title(PSGR_Campaigns::all()[0]) === 'Regalo spedizione','Italian campaign title');
check(PSGR_Localization::campaign_message(PSGR_Campaigns::all()[0]) === 'Scegli il regalo','Italian campaign message');
check(PSGR_Localization::text('free') === 'GRATIS','Italian free label');
check(strpos($plugin->render(),'Regalo spedizione') !== false,'localized Classic selector output');
$GLOBALS['locale'] = 'en_GB';
check(PSGR_Localization::language() === 'en','English language');
check(PSGR_Localization::text('free') === 'FREE','English free label');
check(PSGR_Campaigns::normalise(['name_it'=>'<b>Safe</b>'])['name_it'] === 'Safe','translated input sanitized');
check(PSGR_Campaigns::normalise(['gift_ids'=>['101','102']])['gift_ids'] === [101,102],'product list normalized');
echo 'PASS: '.($GLOBALS['checks'] ?? 0).' alpha04 integration-smoke assertions'.PHP_EOL;
