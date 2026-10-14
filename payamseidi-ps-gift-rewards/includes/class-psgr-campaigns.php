<?php
/** Versioned, forward-compatible campaign repository. */
defined('ABSPATH') || exit;
final class PSGR_Campaigns {
    const OPTION = 'psgr_campaigns_v2';
    public static function defaults(): array {
        return [
            'id' => 'default', 'name' => 'International Shipping Gift',
            'name_en' => '', 'name_it' => '', 'message_en' => '', 'message_it' => '',
            'status' => 'paused', 'priority' => 100, 'starts_on' => '', 'ends_on' => '',
            'min_total' => 69.0, 'max_value' => 10.0,
            'included_countries' => [], 'excluded_countries' => ['IT'],
            'excluded_coupons' => [], 'gift_ids' => [], 'show_progress' => true,
            'reward_type' => 'free_product', 'max_gifts' => 1,
        ];
    }
    public static function all(): array {
        $stored = get_option(self::OPTION, null);
        if ($stored === null || $stored === false) {
            $legacy = get_option('psgr_campaign', []);
            $campaign = self::from_legacy((array) $legacy);
            update_option(self::OPTION, [$campaign], false);
            return [$campaign];
        }
        if (!is_array($stored)) { return []; }
        $campaigns = [];
        foreach ($stored as $row) {
            if (is_array($row) && !empty($row['id'])) { $campaigns[] = self::normalise($row); }
        }
        usort($campaigns, static function($a, $b) { return ($b['priority'] <=> $a['priority']) ?: strcmp($a['id'], $b['id']); });
        return $campaigns;
    }
    private static function from_legacy(array $old): array {
        $c = self::defaults();
        $c['status'] = !empty($old['enabled']) ? 'active' : 'paused';
        foreach (['name', 'min_total', 'max_value'] as $key) {
            if (isset($old[$key])) { $c[$key] = $old[$key]; }
        }
        $c['gift_ids'] = self::ids($old['gift_ids'] ?? '');
        $c['excluded_countries'] = self::codes($old['excluded_countries'] ?? 'IT');
        $c['show_progress'] = !isset($old['show_progress']) || !empty($old['show_progress']);
        return self::normalise($c);
    }
    public static function ids($value): array {
        $raw = is_array($value) ? $value : preg_split('/[,\s]+/', (string) $value);
        return array_values(array_unique(array_filter(array_map('absint', $raw))));
    }
    public static function codes($value): array {
        $raw = is_array($value) ? $value : explode(',', (string) $value);
        $raw = array_map(static function($code) { return strtoupper(trim((string) $code)); }, $raw);
        return array_values(array_unique(array_filter($raw, static function($code) { return (bool) preg_match('/^[A-Z]{2}$/', $code); })));
    }
    public static function coupons($value): array {
        $raw = is_array($value) ? $value : explode(',', (string) $value);
        return array_values(array_unique(array_filter(array_map('sanitize_text_field', array_map('trim', $raw)))));
    }
    public static function normalise(array $input): array {
        $c = array_merge(self::defaults(), $input);
        $c['id'] = sanitize_key($c['id']);
        $c['name'] = sanitize_text_field($c['name']);
        foreach (['name_en', 'name_it', 'message_en', 'message_it'] as $field) {
            $c[$field] = sanitize_text_field((string) ($c[$field] ?? ''));
        }
        $c['status'] = in_array($c['status'], ['active', 'paused', 'draft'], true) ? $c['status'] : 'paused';
        $c['priority'] = max(0, min(100000, (int) $c['priority']));
        $c['min_total'] = max(0, (float) $c['min_total']);
        $c['max_value'] = max(0, (float) $c['max_value']);
        $c['gift_ids'] = self::ids($c['gift_ids']);
        $c['included_countries'] = self::codes($c['included_countries']);
        $c['excluded_countries'] = self::codes($c['excluded_countries']);
        $c['excluded_coupons'] = self::coupons($c['excluded_coupons']);
        $c['show_progress'] = (bool) $c['show_progress'];
        foreach (['starts_on', 'ends_on'] as $field) {
            $v = (string) $c[$field];
            $c[$field] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false ? $v : '';
        }
        // Alpha: free-product campaigns only, one gift per order. No inactive rule bypass.
        $c['reward_type'] = 'free_product';
        $c['max_gifts'] = 1;
        $rules = [
            ['field' => 'shipping_country', 'operator' => 'neq', 'value' => ''],
            ['field' => 'cart_subtotal', 'operator' => 'gte', 'value' => $c['min_total']],
        ];
        if ($c['included_countries']) { $rules[] = ['field' => 'shipping_country', 'operator' => 'in', 'value' => $c['included_countries']]; }
        if ($c['excluded_countries']) { $rules[] = ['field' => 'shipping_country', 'operator' => 'not_in', 'value' => $c['excluded_countries']]; }
        foreach ($c['excluded_coupons'] as $code) { $rules[] = ['field' => 'coupon_codes', 'operator' => 'not_contains', 'value' => [$code]]; }
        $c['rule_groups'] = [['relation' => 'AND', 'rules' => $rules]];
        return $c;
    }
    public static function get(string $id): ?array {
        foreach (self::all() as $campaign) { if ($campaign['id'] === $id) { return $campaign; } }
        return null;
    }
    public static function put(array $campaign): void {
        $campaign = self::normalise($campaign);
        $stored = self::all(); $found = false;
        foreach ($stored as $i => $row) { if ($row['id'] === $campaign['id']) { $stored[$i] = $campaign; $found = true; break; } }
        if (!$found) { $stored[] = $campaign; }
        update_option(self::OPTION, $stored, false);
    }
    public static function remove(string $id): bool {
        if ($id === 'default') { return false; }
        $stored = self::all();
        $next = array_values(array_filter($stored, static function($c) use ($id) { return $c['id'] !== $id; }));
        if (count($next) === count($stored)) { return false; }
        update_option(self::OPTION, $next, false);
        return true;
    }
    public static function selected(array $context): ?array {
        foreach (self::all() as $campaign) {
            if (PSGR_Rules::campaign_matches($campaign, $context)) { return $campaign; }
        }
        return null;
    }
}
