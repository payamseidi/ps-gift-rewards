<?php
/** WooCommerce Blocks IntegrationInterface + Store API bridge. */
defined('ABSPATH') || exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;

final class PSGR_Blocks_Integration implements IntegrationInterface {
    public function get_name() { return 'ps-gift-rewards'; }
    public function initialize() {
        wp_register_script(
            'psgr-blocks',
            PSGR_URL . 'assets/blocks.js',
            ['wp-element', 'wp-plugins', 'wp-i18n', 'wc-blocks-checkout'],
            PSGR_VERSION,
            true
        );
        wp_set_script_translations('psgr-blocks', 'payamseidi-ps-gift-rewards');
        wp_register_style('psgr-blocks', PSGR_URL . 'assets/gifts.css', [], PSGR_VERSION);
        wp_enqueue_style('psgr-blocks');
    }
    public function get_script_handles() { return ['psgr-blocks']; }
    public function get_editor_script_handles() { return []; }
    public function get_script_data() { return []; }
}

final class PSGR_Blocks {
    const NS = 'ps-gift-rewards';
    public static function boot() {
        if (!interface_exists(IntegrationInterface::class) || !class_exists(CartSchema::class)
            || !function_exists('woocommerce_store_api_register_endpoint_data')
            || !function_exists('woocommerce_store_api_register_update_callback')) { return; }

        woocommerce_store_api_register_endpoint_data([
            'endpoint' => CartSchema::IDENTIFIER,
            'namespace' => self::NS,
            'data_callback' => [__CLASS__, 'data'],
            'schema_callback' => [__CLASS__, 'schema'],
            'schema_type' => ARRAY_A,
        ]);
        woocommerce_store_api_register_update_callback([
            'namespace' => self::NS,
            'callback' => [__CLASS__, 'update'],
        ]);
        foreach (['woocommerce_blocks_cart_block_registration', 'woocommerce_blocks_checkout_block_registration'] as $hook) {
            add_action($hook, [__CLASS__, 'register_integration']);
        }
    }
    public static function register_integration($registry) {
        $registry->register(new PSGR_Blocks_Integration());
    }
    public static function data(): array {
        return PSGR_Plugin::instance()->store_payload();
    }
    public static function schema(): array {
        return [
            'visible' => ['description' => 'Display the reward selector', 'type' => 'boolean', 'readonly' => true],
            'eligible' => ['description' => 'Cart qualifies for a gift', 'type' => 'boolean', 'readonly' => true],
            'campaign_id' => ['type' => 'string', 'readonly' => true],
            'title' => ['type' => 'string', 'readonly' => true],
            'strings' => ['type' => 'object', 'readonly' => true, 'properties' => [
                'gift_rewards' => ['type' => 'string'],
                'choose_intro' => ['type' => 'string'],
                'unlock_intro' => ['type' => 'string'],
                'choose' => ['type' => 'string'],
                'selected' => ['type' => 'string'],
                'remove' => ['type' => 'string'],
                'free' => ['type' => 'string'],
                'no_gifts' => ['type' => 'string'],
            ]],
            'message' => ['type' => 'string', 'readonly' => true],
            'remaining' => ['type' => 'string', 'readonly' => true],
            'selected_id' => ['type' => 'integer', 'readonly' => true],
            'gifts' => [
                'type' => 'array', 'readonly' => true,
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'price' => ['type' => 'string'],
                        'image' => ['type' => 'string'],
                    ],
                ],
            ],
        ];
    }
    public static function update($data): void {
        $id = is_array($data) && isset($data['id']) && is_scalar($data['id']) ? absint($data['id']) : 0;
        if (!is_array($data) || !isset($data['action']) || !in_array($data['action'], ['choose', 'remove'], true)) {
            throw new RouteException('PSGR-301', __('We could not understand that gift selection.', 'payamseidi-ps-gift-rewards'), 400);
        }
        if ($data['action'] === 'choose' && $id < 1) {
            throw new RouteException('PSGR-302', __('Please select a valid gift.', 'payamseidi-ps-gift-rewards'), 400);
        }
        $result = PSGR_Plugin::instance()->select_gift($data['action'] === 'remove' ? 0 : $id);
        if (is_wp_error($result)) {
            throw new RouteException($result->get_error_code(), $result->get_error_message(), 422);
        }
    }
}
