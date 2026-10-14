<?php
/** Pure, side-effect-free rule evaluator. No direct WooCommerce or WP calls. */
defined('ABSPATH') || exit;
final class PSGR_Rules {
    private const FIELDS = ['shipping_country', 'cart_subtotal', 'coupon_codes', 'customer_logged_in', 'shipping_required'];
    private const OPS = ['eq', 'neq', 'in', 'not_in', 'gte', 'lte', 'contains', 'not_contains'];

    /** Any matching group qualifies. Within each group, relation controls its clauses. */
    public static function matches(array $groups, array $context): bool {
        if (!$groups) { return false; } // Fail closed when no rules exist.
        foreach ($groups as $group) {
            if (!is_array($group) || empty($group['rules']) || !is_array($group['rules'])) { continue; }
            $and = strtoupper((string) ($group['relation'] ?? 'AND')) !== 'OR';
            $pass = $and;
            foreach ($group['rules'] as $rule) {
                $ok = is_array($rule) && self::test($rule, $context);
                if ($and && !$ok) { $pass = false; break; }
                if (!$and && $ok) { $pass = true; break; }
            }
            if ($pass) { return true; }
        }
        return false;
    }
    public static function test(array $rule, array $context): bool {
        $field = (string) ($rule['field'] ?? '');
        $op = (string) ($rule['operator'] ?? '');
        if (!in_array($field, self::FIELDS, true) || !in_array($op, self::OPS, true) || !array_key_exists($field, $context)) { return false; }
        $actual = $context[$field];
        $expected = $rule['value'] ?? null;
        if (in_array($op, ['gte', 'lte'], true)) {
            if (!is_numeric($actual) || !is_numeric($expected)) { return false; }
            return $op === 'gte' ? (float) $actual + 0.000001 >= (float) $expected : (float) $actual <= (float) $expected + 0.000001;
        }
        if (in_array($op, ['in', 'not_in', 'contains', 'not_contains'], true)) {
            $values = is_array($expected) ? $expected : array_filter(array_map('trim', explode(',', (string) $expected)));
            $values = array_map([self::class, 'canonical'], $values);
            if ($op === 'in' || $op === 'not_in') {
                $found = in_array(self::canonical($actual), $values, true);
                return $op === 'in' ? $found : !$found;
            }
            if (!is_array($actual)) { return false; }
            $found = count(array_intersect(array_map([self::class, 'canonical'], $actual), $values)) > 0;
            return $op === 'contains' ? $found : !$found;
        }
        if (is_array($actual) || is_array($expected) || $expected === null) { return false; }
        $equal = self::canonical($actual) === self::canonical($expected);
        return $op === 'eq' ? $equal : ($op === 'neq' && !$equal);
    }
    private static function canonical($value): string {
        if (is_bool($value)) { return $value ? '1' : '0'; }
        return strtoupper(trim((string) $value));
    }
    /** Schedule uses the site's local YYYY-MM-DD, passed by the caller. */
    public static function campaign_matches(array $campaign, array $context): bool {
        if (($campaign['status'] ?? '') !== 'active') { return false; }
        $date = (string) ($context['local_date'] ?? '');
        if (!$date) { return false; }
        $start = (string) ($campaign['starts_on'] ?? '');
        $end = (string) ($campaign['ends_on'] ?? '');
        if ($start !== '' && $date < $start) { return false; }
        if ($end !== '' && $date > $end) { return false; }
        return self::matches((array) ($campaign['rule_groups'] ?? []), $context);
    }
}
