<?php
defined('ABSPATH') || exit;
/** PS Gift Rewards single-campaign staging implementation. */
final class PSGR_Plugin {
    const OPT = 'psgr_campaign';
    const MARK = '_psgr_reward';
    private static $instance;
    public static function instance() { return self::$instance ?: (self::$instance = new self()); }
    private function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_post_psgr_save', [$this, 'save']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('woocommerce_before_cart_totals', [$this, 'selector']);
        add_action('woocommerce_review_order_before_payment', [$this, 'selector']);
        add_shortcode('ps_gift_rewards', [$this, 'render']);
        add_action('wp_ajax_psgr_choose', [$this, 'choose']);
        add_action('wp_ajax_nopriv_psgr_choose', [$this, 'choose']);
        add_action('woocommerce_before_calculate_totals', [$this, 'reconcile'], 9999);
        add_action('woocommerce_check_cart_items', [$this, 'validate']);
        add_action('woocommerce_checkout_process', [$this, 'validate']);
        add_filter('woocommerce_get_item_data', [$this, 'item_data'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'order_meta'], 10, 4);
        add_filter('woocommerce_cart_item_quantity', [$this, 'lock_quantity'], 10, 3);
        add_action('woocommerce_cart_emptied', [$this, 'clear']);
    }
    private function defaults() { return ['enabled'=>0,'name'=>'International Shipping Gift','min_total'=>'69','max_value'=>'10','excluded_countries'=>'IT','gift_ids'=>'','show_progress'=>1]; }
    private function cfg() { return wp_parse_args((array) get_option(self::OPT, []), $this->defaults()); }
    public function menu() {
        $parent = class_exists('PS_Tools_Core') ? 'ps-tools' : 'woocommerce';
        add_submenu_page($parent, 'PS Gift Rewards', 'Gift Rewards', 'manage_woocommerce', 'ps-gift-rewards', [$this,'admin']);
    }
    public function admin() {
        if (!current_user_can('manage_woocommerce')) { wp_die(esc_html__('Not permitted.', 'payamseidi-ps-gift-rewards')); }
        $c=$this->cfg();
        echo '<div class="wrap"><h1>PS Gift Rewards <small>0.1.0-alpha.1 · Payam Seidi</small></h1>';
        echo '<p>Single-campaign alpha. Classic Cart and Checkout only; do not enable on production without staging tests.</p>';
        if (isset($_GET['saved'])) { echo '<div class="notice notice-success"><p>Campaign saved.</p></div>'; }
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="psgr_save">';
        wp_nonce_field('psgr_save');
        echo '<table class="form-table">';
        $rows = [
            ['enabled','Campaign active','checkbox'],['name','Campaign name','text'],
            ['min_total','Minimum eligible product total (store currency)','number'],
            ['max_value','Maximum gift sale value (store currency)','number'],
            ['excluded_countries','Excluded destination country codes (e.g. IT,FR)','text'],
            ['gift_ids','Gift product or variation IDs (comma separated)','text'],
            ['show_progress','Show unlock progress','checkbox'],
        ];
        foreach ($rows as $r) {
            [$key,$label,$type]=$r;
            echo '<tr><th scope="row"><label for="psgr_'.esc_attr($key).'">'.esc_html($label).'</label></th><td>';
            if ($type==='checkbox') {
                echo '<input type="checkbox" id="psgr_'.esc_attr($key).'" name="psgr['.esc_attr($key).']" value="1" '.checked(!empty($c[$key]),true,false).'>';
            } else {
                echo '<input class="regular-text" type="'.esc_attr($type).'" id="psgr_'.esc_attr($key).'" name="psgr['.esc_attr($key).']" value="'.esc_attr($c[$key]).'" '.($type==='number'?'min="0" step="0.01"':'').'>';
            }
            echo '</td></tr>';
        }
        echo '</table>'; submit_button('Save campaign');
        echo '</form><p>Shortcode: <code>[ps_gift_rewards]</code></p></div>';
    }
    public function save() {
        if (!current_user_can('manage_woocommerce')) { wp_die('Forbidden','',['response'=>403]); }
        check_admin_referer('psgr_save');
        $p=isset($_POST['psgr']) && is_array($_POST['psgr']) ? wp_unslash($_POST['psgr']) : [];
        $ids=array_unique(array_filter(array_map('absint',preg_split('/[,\s]+/',(string)($p['gift_ids']??'')))));
        $codes=array_unique(array_map('strtoupper',array_map('trim',explode(',',sanitize_text_field($p['excluded_countries']??'IT')))));
        $codes=array_values(array_filter($codes,static function($v){return (bool)preg_match('/^[A-Z]{2}$/',$v);}));
        update_option(self::OPT,[
            'enabled'=>empty($p['enabled'])?0:1,
            'name'=>sanitize_text_field($p['name']??''),
            'min_total'=>(string)max(0,(float)($p['min_total']??69)),
            'max_value'=>(string)max(0,(float)($p['max_value']??10)),
            'excluded_countries'=>implode(',',$codes),
            'gift_ids'=>implode(',',$ids),
            'show_progress'=>empty($p['show_progress'])?0:1,
        ],false);
        wp_safe_redirect(add_query_arg(['page'=>'ps-gift-rewards','saved'=>1],admin_url('admin.php'))); exit;
    }
    private function gift_ids($c) { return array_filter(array_map('absint',explode(',',$c['gift_ids']))); }
    private function country() {
        return WC()->customer ? strtoupper((string)WC()->customer->get_shipping_country()) : '';
    }
    private function subtotal($cart) {
        $sum=0.0;
        foreach ($cart->get_cart() as $line) {
            if (empty($line[self::MARK])) { $sum+=(float)($line['line_total']??0); }
        }
        return $sum;
    }
    private function eligible($cart,$c) {
        if (empty($c['enabled']) || !$cart || !$this->country()) { return false; }
        if (in_array($this->country(),array_map('trim',explode(',',$c['excluded_countries'])),true)) { return false; }
        return $this->subtotal($cart)+0.000001 >= (float)$c['min_total'];
    }
    private function gifts($c) {
        $out=[];
        foreach ($this->gift_ids($c) as $id) {
            $p=wc_get_product($id);
            if (!$p || !$p->is_purchasable() || !$p->is_in_stock() || $p->is_type('grouped') || $p->is_type('external') || $p->is_type('variable')) { continue; }
            $price=(float)$p->get_price();
            if ($price<0 || $price>(float)$c['max_value']) { continue; }
            $out[$id]=$p;
        }
        return $out;
    }
    public function assets() {
        $post=get_post();
        if (!is_cart() && !is_checkout() && !($post && has_shortcode((string)$post->post_content,'ps_gift_rewards'))) { return; }
        wp_enqueue_style('psgr',PSGR_URL.'assets/gifts.css',[],PSGR_VERSION);
        wp_enqueue_script('psgr',PSGR_URL.'assets/gifts.js',['jquery'],PSGR_VERSION,true);
        wp_localize_script('psgr','PSGR',['ajax'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('psgr_choose'),'error'=>__('Unable to update your gift. Please try again.','payamseidi-ps-gift-rewards')]);
    }
    public function selector() { echo $this->render(); }
    public function render() {
        if (!function_exists('WC') || !WC()->cart) { return ''; }
        $cart=WC()->cart; $c=$this->cfg();
        if (empty($c['enabled'])) { return ''; }
        $eligible=$this->eligible($cart,$c);
        if (!$eligible && empty($c['show_progress'])) { return ''; }
        ob_start();
        echo '<section class="psgr-panel"><h3>'.esc_html($c['name']).'</h3>';
        if (!$eligible) {
            $remaining=max(0,(float)$c['min_total']-$this->subtotal($cart));
            echo '<p>'.esc_html(sprintf(__('Add %s more to unlock a free gift.','payamseidi-ps-gift-rewards'),wp_strip_all_tags(wc_price($remaining)))).'</p>';
        } else {
            echo '<p>'.esc_html__('Choose one complimentary gift.','payamseidi-ps-gift-rewards').'</p><div class="psgr-grid">';
            $selected=$this->selected($cart);
            $gifts=$this->gifts($c);
            foreach ($gifts as $id=>$product) {
                echo '<div class="psgr-card">'.wp_kses_post($product->get_image('woocommerce_thumbnail')).'<strong>'.esc_html($product->get_name()).'</strong><span>'.wp_kses_post(wc_price($product->get_price())).' · <b>FREE</b></span>';
                echo '<button type="button" class="button psgr-choice" data-id="'.esc_attr($id).'" '.disabled($selected===$id,true,false).'>'.esc_html($selected===$id?'Selected':'Choose gift').'</button></div>';
            }
            echo '</div>';
            if (!$gifts) { echo '<p>'.esc_html__('No gifts are available.','payamseidi-ps-gift-rewards').'</p>'; }
            if ($selected) { echo '<button type="button" class="psgr-choice psgr-remove" data-id="0">'.esc_html__('Remove gift','payamseidi-ps-gift-rewards').'</button>'; }
        }
        echo '<p class="psgr-status" role="status" aria-live="polite"></p></section>';
        return ob_get_clean();
    }
    private function selected($cart) {
        foreach ($cart->get_cart() as $line) {
            if (!empty($line[self::MARK])) { return absint($line['_psgr_gift_id']??0); }
        }
        return 0;
    }
    public function choose() {
        check_ajax_referer('psgr_choose','nonce');
        if (!WC()->cart) { wp_send_json_error(['message'=>'Cart unavailable'],400); }
        $id=isset($_POST['id'])?absint($_POST['id']):0;
        $c=$this->cfg(); $cart=WC()->cart;
        if ($id && (!$this->eligible($cart,$c) || !isset($this->gifts($c)[$id]))) {
            wp_send_json_error(['message'=>__('This gift is not available for your order.','payamseidi-ps-gift-rewards')],422);
        }
        foreach ($cart->get_cart() as $key=>$line) {
            if (!empty($line[self::MARK])) { $cart->remove_cart_item($key); }
        }
        if ($id) {
            $p=wc_get_product($id);
            $parent=$p->is_type('variation')?$p->get_parent_id():$id;
            $variation=$p->is_type('variation')?$id:0;
            $added=$cart->add_to_cart($parent,1,$variation,[],[
                self::MARK=>1,'_psgr_gift_id'=>$id,
                '_psgr_original_price'=>(string)$p->get_price(),'_psgr_campaign'=>'default',
            ]);
            if (!$added) { wp_send_json_error(['message'=>__('Unable to add this gift.','payamseidi-ps-gift-rewards')],422); }
        }
        $cart->calculate_totals();
        wp_send_json_success(['message'=>$id?'Gift selected.':'Gift removed.']);
    }
    public function reconcile($cart) {
        if (is_admin() && !wp_doing_ajax()) { return; }
        static $busy=false;
        if ($busy) { return; }
        $busy=true; $c=$this->cfg(); $allowed=$this->gifts($c); $seen=false;
        foreach ($cart->get_cart() as $key=>$line) {
            if (empty($line[self::MARK])) { continue; }
            $id=absint($line['_psgr_gift_id']??0);
            if ($seen || !$this->eligible($cart,$c) || !isset($allowed[$id]) || (int)$line['quantity']!==1) {
                $cart->remove_cart_item($key); continue;
            }
            $seen=true;
            if (isset($line['data']) && $line['data'] instanceof WC_Product) { $line['data']->set_price(0); }
        }
        $busy=false;
    }
    public function validate() {
        if (!WC()->cart) { return; }
        $c=$this->cfg(); $count=0; $allowed=$this->gifts($c);
        foreach (WC()->cart->get_cart() as $line) {
            if (empty($line[self::MARK])) { continue; }
            $count++; $id=absint($line['_psgr_gift_id']??0);
            if (!$this->eligible(WC()->cart,$c) || !isset($allowed[$id]) || (int)$line['quantity']!==1) {
                wc_add_notice(__('Your selected gift is no longer eligible.','payamseidi-ps-gift-rewards'),'error'); return;
            }
        }
        if ($count>1) { wc_add_notice(__('Only one free gift is allowed.','payamseidi-ps-gift-rewards'),'error'); }
    }
    public function item_data($data,$line) {
        if (!empty($line[self::MARK])) { $data[]=['key'=>'Reward','value'=>'Complimentary gift']; }
        return $data;
    }
    public function order_meta($item,$key,$values,$order) {
        if (!empty($values[self::MARK])) {
            $item->add_meta_data('_psgr_reward',1,true);
            $item->add_meta_data('_psgr_campaign','default',true);
            $item->add_meta_data('_psgr_original_price',wc_format_decimal($values['_psgr_original_price']??0),true);
            $item->add_meta_data('Reward','Complimentary gift',true);
        }
    }
    public function lock_quantity($html,$key,$line) { return !empty($line[self::MARK])?'1':$html; }
    public function clear() { if (WC()->session) { WC()->session->__unset('psgr_choice'); } }
}
