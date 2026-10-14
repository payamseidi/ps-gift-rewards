<?php
/**
 * Plugin Name: PS Gift Rewards for WooCommerce
 * Plugin URI: https://payamseidi.com/
 * Description: Configurable WooCommerce gift campaigns from Payam Seidi's PS Tools family.
 * Version: 0.4.0-alpha.1
 * Author: Payam Seidi
 * Author URI: https://payamseidi.com/
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: payamseidi-ps-gift-rewards
 */
defined('ABSPATH') || exit;
define('PSGR_VERSION', '0.4.0-alpha.1');
define('PSGR_PATH', plugin_dir_path(__FILE__));
define('PSGR_URL', plugin_dir_url(__FILE__));
add_action('before_woocommerce_init', static function () {
    if (class_exists('Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});
add_action('plugins_loaded', static function () {
    if (!class_exists('WooCommerce')) { return; }
    require_once PSGR_PATH.'includes/class-psgr-localization.php';
    require_once PSGR_PATH.'includes/class-psgr-rules.php';
    require_once PSGR_PATH.'includes/class-psgr-campaigns.php';
    require_once PSGR_PATH.'includes/class-psgr.php';
    PSGR_Plugin::instance();
}, 20);

/** Register Store API & Block integration only after WooCommerce Blocks loads. */
add_action('woocommerce_blocks_loaded', static function () {
    if (!class_exists('WooCommerce')) { return; }
    // WooCommerce versions may fire this hook before our plugins_loaded callback.
    if (!class_exists('PSGR_Plugin')) {
        require_once PSGR_PATH . 'includes/class-psgr-localization.php';
        require_once PSGR_PATH . 'includes/class-psgr-rules.php';
        require_once PSGR_PATH . 'includes/class-psgr-campaigns.php';
        require_once PSGR_PATH . 'includes/class-psgr.php';
    }
    if (!interface_exists('Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface')) { return; }
    require_once PSGR_PATH . 'includes/class-psgr-blocks.php';
    PSGR_Blocks::boot();
}, 20);
