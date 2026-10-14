<?php
/** Customer-facing EN/IT copy with Polylang-aware locale resolution. */
defined('ABSPATH') || exit;
final class PSGR_Localization {
    public static function language(): string {
        if (function_exists('pll_current_language')) {
            $slug = pll_current_language('slug');
            if (is_string($slug) && $slug !== '') {
                return strtolower(substr($slug, 0, 2)) === 'it' ? 'it' : 'en';
            }
        }
        return strpos(strtolower((string) get_locale()), 'it') === 0 ? 'it' : 'en';
    }
    public static function strings(): array {
        $italian = [
            'gift_rewards' => 'I tuoi regali',
            'choose_intro' => 'Scegli un regalo gratuito da aggiungere al tuo ordine.',
            'unlock_intro' => 'Aggiungi altri prodotti per sbloccare un regalo.',
            'unlock_prefix' => 'Aggiungi ancora %s al carrello per ricevere un regalo gratuito.',
            'choose' => 'Scegli il regalo',
            'selected' => 'Selezionato',
            'remove' => 'Rimuovi il regalo',
            'free' => 'GRATIS',
            'no_gifts' => 'Al momento non ci sono regali disponibili.',
            'reward' => 'Regalo',
            'complimentary' => 'Regalo gratuito',
            'error' => 'Non siamo riusciti ad aggiornare il regalo. Riprova.',
        ];
        $english = [
            'gift_rewards' => __('Gift rewards', 'payamseidi-ps-gift-rewards'),
            'choose_intro' => __('Choose one complimentary gift with your order.', 'payamseidi-ps-gift-rewards'),
            'unlock_intro' => __('Add more products to unlock a complimentary gift.', 'payamseidi-ps-gift-rewards'),
            'unlock_prefix' => __('Add %s more to unlock a complimentary gift.', 'payamseidi-ps-gift-rewards'),
            'choose' => __('Choose gift', 'payamseidi-ps-gift-rewards'),
            'selected' => __('Selected', 'payamseidi-ps-gift-rewards'),
            'remove' => __('Remove gift', 'payamseidi-ps-gift-rewards'),
            'free' => __('FREE', 'payamseidi-ps-gift-rewards'),
            'no_gifts' => __('No gifts are currently available.', 'payamseidi-ps-gift-rewards'),
            'reward' => __('Reward', 'payamseidi-ps-gift-rewards'),
            'complimentary' => __('Complimentary gift', 'payamseidi-ps-gift-rewards'),
            'error' => __('We could not update your gift. Try again.', 'payamseidi-ps-gift-rewards'),
        ];
        return self::language() === 'it' ? $italian : $english;
    }
    public static function text(string $key): string { return self::strings()[$key] ?? ''; }
    public static function campaign_title(array $campaign): string {
        $key = self::language() === 'it' ? 'name_it' : 'name_en';
        return trim((string) ($campaign[$key] ?? '')) ?: (string) ($campaign['name'] ?? '');
    }
    public static function campaign_message(array $campaign): string {
        $key = self::language() === 'it' ? 'message_it' : 'message_en';
        return trim((string) ($campaign[$key] ?? '')) ?: self::text('choose_intro');
    }
}
