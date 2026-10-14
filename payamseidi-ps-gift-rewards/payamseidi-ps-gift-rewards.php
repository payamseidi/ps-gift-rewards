<?php
/**
 * Plugin Name: PS Gift Rewards for WooCommerce
 * Plugin URI: https://payamseidi.com/
 * Description: Configurable gift campaigns for WooCommerce, part of the PS Tools family.
 * Version: 0.1.0-alpha.1
 * Author: Payam Seidi
 * Author URI: https://payamseidi.com/
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Text Domain: payamseidi-ps-gift-rewards
 */
defined('ABSPATH') || exit;
define('PSGR_VERSION', '0.1.0-alpha.1');
define('PSGR_PATH', plugin_dir_path(__FILE__));
define('PSGR_URL', plugin_dir_url(__FILE__));
add_action('before_woocommerce_init', static function () {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \\Automattic\\WooCommerce\\Utilities\\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
add_action('plugins_loaded', static function () {
    if (!class_exists('WooCommerce')) { return; }
    require_once PSGR_PATH . 'includes/class-psgr.php';
    PSGR_Plugin::instance();
}, 20);
