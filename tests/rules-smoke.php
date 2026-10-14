<?php
/* Dependency-free smoke tests for rule evaluation and campaign migration. */
define('ABSPATH', __DIR__);
$GLOBALS['options'] = [];
function get_option($k, $fallback = false) { return array_key_exists($k, $GLOBALS['options']) ? $GLOBALS['options'][$k] : $fallback; }
function update_option($k, $v, $autoload = null) { $GLOBALS['options'][$k] = $v; return true; }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($v)); }
function sanitize_text_field($v) { return strip_tags((string) $v); }
function absint($n) { return abs((int)$n); }
require_once dirname(__DIR__).'/payamseidi-ps-gift-rewards/includes/class-psgr-rules.php';
require_once dirname(__DIR__).'/payamseidi-ps-gift-rewards/includes/class-psgr-campaigns.php';
$assertions = 0;
function check($actual, $expected, $message) {
    global $assertions; ++$assertions;
    if ($actual !== $expected) { fwrite(STDERR, "FAIL $message; got ".var_export($actual, true)."\n"); exit(1); }
}
$campaign = PSGR_Campaigns::normalise(array_merge(PSGR_Campaigns::defaults(), ['status'=>'active', 'gift_ids'=>'12,14', 'excluded_coupons'=>'FREE,VIP']));
$ctx = ['shipping_country'=>'DE','cart_subtotal'=>85,'coupon_codes'=>[],'customer_logged_in'=>false,'shipping_required'=>true,'local_date'=>'2026-10-14'];
check(PSGR_Rules::campaign_matches($campaign, $ctx),true,'Germany, 85');
check(PSGR_Rules::campaign_matches($campaign, array_merge($ctx,['cart_subtotal'=>50])),false,'threshold');
check(PSGR_Rules::campaign_matches($campaign, array_merge($ctx,['shipping_country'=>'IT'])),false,'Italy excluded');
check(PSGR_Rules::campaign_matches($campaign, array_merge($ctx,['shipping_country'=>''])),false,'unknown destination');
check(PSGR_Rules::campaign_matches($campaign, array_merge($ctx,['coupon_codes'=>['vip']])),false,'excluded coupon');
check(PSGR_Rules::campaign_matches($campaign, array_merge($ctx,['cart_subtotal'=>69])),true,'exact threshold');
check(PSGR_Rules::campaign_matches($campaign, array_merge($ctx,['cart_subtotal'=>68.99])),false,'below threshold');
check(PSGR_Rules::test(['field'=>'bad','operator'=>'eq','value'=>1],$ctx),false,'unknown rule fails closed');
check(PSGR_Rules::matches([], $ctx),false,'empty groups fail closed');
check(PSGR_Campaigns::ids('12,12,-14,broken'),[12,14],'deduped ids');
check(PSGR_Campaigns::codes('it,DE,x,123'),['IT','DE'],'valid ISO country codes');
check($campaign['max_gifts'],1,'one gift hard limit');
check($campaign['gift_ids'],[12,14],'gift ids kept');
$scheduled = array_merge($campaign,['starts_on'=>'2026-10-15','ends_on'=>'2026-10-30']);
check(PSGR_Rules::campaign_matches($scheduled,$ctx),false,'scheduled future');
check(PSGR_Rules::campaign_matches($scheduled,array_merge($ctx,['local_date'=>'2026-10-15'])),true,'schedule starts on date');
$restricted = PSGR_Campaigns::normalise(array_merge($campaign,['included_countries'=>['FR']]));
check(PSGR_Rules::campaign_matches($restricted,$ctx),false,'country allow list');
check(PSGR_Rules::campaign_matches($restricted,array_merge($ctx,['shipping_country'=>'FR'])),true,'allow list match');
$GLOBALS['options']['psgr_campaign']=['enabled'=>1,'name'=>'Legacy offer','gift_ids'=>'2,4','min_total'=>'69','max_value'=>'10','excluded_countries'=>'IT'];
$all = PSGR_Campaigns::all();
check(count($all),1,'legacy migration count');
check($all[0]['status'],'active','legacy enabled');
check($all[0]['gift_ids'],[2,4],'legacy gifts');
PSGR_Campaigns::put(array_merge($campaign,['id'=>'second','priority'=>200]));
check(PSGR_Campaigns::selected($ctx)['id'],'second','priority selector');
check(PSGR_Campaigns::remove('second'),true,'delete additional campaign');
check(PSGR_Campaigns::remove('default'),false,'protect default');
echo "PASS: {$assertions} rule and migration assertions\n";
