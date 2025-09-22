<?php
/**
 * Plugin Name: WP Genova WooCommerce
 * Plugin URI:  https://genova.co.ke/wp-genova-woocommerce
 * Description: Genova integration: embed product insurance in WooCommerce checkout and provide claim management. Configurable API base & API key in admin settings. ActionScheduler-based retries and logging included.
 * Version:     1.0.0
 * Author:      Evans Wanguba
 * Text Domain: wp-genova-woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
if (!defined('WP_GENOVA_PLUGIN_DIR')) define('WP_GENOVA_PLUGIN_DIR', plugin_dir_path(__FILE__));
if (!defined('WP_GENOVA_PLUGIN_URL')) define('WP_GENOVA_PLUGIN_URL', plugin_dir_url(__FILE__));
if (!defined('WP_GENOVA_OPTION_GROUP')) define('WP_GENOVA_OPTION_GROUP', 'wp_genova_options');
if (!defined('WP_GENOVA_SETTINGS')) define('WP_GENOVA_SETTINGS', 'wp_genova_settings');

// Include Action Scheduler if not present (WooCommerce ships it). If not present, we suggest installing the Action Scheduler plugin.
add_action('admin_notices', function() {
    if (!class_exists('\ActionScheduler')) {
        echo '<div class="notice notice-warning"><p>' . esc_html__('Action Scheduler not found. It is recommended to have WooCommerce or Action Scheduler installed for background retries to work.', 'wp-genova-woocommerce') . '</p></div>';
    }
});

// Activation hook: set defaults
register_activation_hook(__FILE__, function () {
    $defaults = [
        'api_base' => '',
        'api_key' => '',
        'api_secret' => '',
        'purchase_trigger' => 'order_processed', // or payment_complete
        'max_retries' => 3
    ];
    if (!get_option(WP_GENOVA_SETTINGS)) {
        add_option(WP_GENOVA_SETTINGS, $defaults);
    }
});

// Load textdomain
add_action('init', function () {
    load_plugin_textdomain('wp-genova-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

/**
 * Encryption helpers for storing API key encrypted in options.
 * Uses openssl with AUTH_SALT as key and AES-256-CBC.
 */
function wp_genova_encrypt($plaintext) {
    if (empty($plaintext)) return '';
    $key = substr(hash('sha256', AUTH_SALT), 0, 32);
    $iv = openssl_random_pseudo_bytes(16);
    $cipher = 'AES-256-CBC';
    $ciphertext = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $ciphertext);
}
function wp_genova_decrypt($b64) {
    if (empty($b64)) return '';
    $raw = base64_decode($b64);
    if ($raw === false || strlen($raw) < 17) return '';
    $iv = substr($raw, 0, 16);
    $ciphertext = substr($raw, 16);
    $key = substr(hash('sha256', AUTH_SALT), 0, 32);
    $cipher = 'AES-256-CBC';
    $plaintext = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    return $plaintext === false ? '' : $plaintext;
}

// Admin settings page
add_action('admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        __('Genova Agency', 'wp-genova-woocommerce'),
        __('Genova Agency', 'wp-genova-woocommerce'),
        'manage_options',
        'wp-genova-settings',
        'wp_genova_settings_page'
    );
});

function wp_genova_settings_page() {
    if (!current_user_can('manage_options')) return;

    // Save settings if post
    if (isset($_POST['wp_genova_settings_nonce']) && wp_verify_nonce($_POST['wp_genova_settings_nonce'], 'wp_genova_save')) {
        $opts = get_option(WP_GENOVA_SETTINGS, []);
        $api_base = isset($_POST['api_base']) ? esc_url_raw(trim($_POST['api_base'])) : '';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field(trim($_POST['api_key'])) : '';
        $purchase_trigger = isset($_POST['purchase_trigger']) && in_array($_POST['purchase_trigger'], ['order_processed','payment_complete']) ? $_POST['purchase_trigger'] : 'order_processed';
        $max_retries = isset($_POST['max_retries']) ? intval($_POST['max_retries']) : 3;
        $opts['api_base'] = rtrim($api_base, '/');
        // encrypt api key before saving
        $opts['api_key'] = $api_key ? wp_genova_encrypt($api_key) : '';
        $opts['purchase_trigger'] = $purchase_trigger;
        $opts['max_retries'] = max(0, $max_retries);
        update_option(WP_GENOVA_SETTINGS, $opts);
        echo '<div class="updated"><p>' . esc_html__('Settings saved.', 'wp-genova-woocommerce') . '</p></div>';
    }

    $opts = get_option(WP_GENOVA_SETTINGS, ['api_base' => '', 'api_key' => '', 'purchase_trigger' => 'order_processed', 'max_retries' => 3]);
    $decrypted_key = wp_genova_decrypt($opts['api_key']);
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Genova Agency Settings', 'wp-genova-woocommerce'); ?></h1>
        <form method="post">
            <?php wp_nonce_field('wp_genova_save', 'wp_genova_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="api_base"><?php esc_html_e('Insurance API Base URL', 'wp-genova-woocommerce'); ?></label></th>
                    <td><input type="text" id="api_base" name="api_base" value="<?php echo esc_attr($opts['api_base']); ?>" style="width:400px" placeholder="https://genova.co.ke/api/v1" /></td>
                </tr>
                <tr>
                    <th><label for="api_key"><?php esc_html_e('API Key (optional)', 'wp-genova-woocommerce'); ?></label></th>
                    <td><input type="password" id="api_key" name="api_key" value="<?php echo esc_attr($decrypted_key); ?>" style="width:400px" /></td>
                </tr>
                <tr>
                    <th><label for="purchase_trigger"><?php esc_html_e('Purchase trigger', 'wp-genova-woocommerce'); ?></label></th>
                    <td>
                        <select id="purchase_trigger" name="purchase_trigger">
                            <option value="order_processed" <?php selected($opts['purchase_trigger'], 'order_processed'); ?>><?php esc_html_e('Order processed (default)', 'wp-genova-woocommerce'); ?></option>
                            <option value="payment_complete" <?php selected($opts['purchase_trigger'], 'payment_complete'); ?>><?php esc_html_e('Payment complete (after capture)', 'wp-genova-woocommerce'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Choose when the plugin calls the insurance purchase endpoint.', 'wp-genova-woocommerce'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="max_retries"><?php esc_html_e('Max purchase retries', 'wp-genova-woocommerce'); ?></label></th>
                    <td><input type="number" id="max_retries" name="max_retries" value="<?php echo esc_attr($opts['max_retries'] ?? 3); ?>" min="0" max="10" /></td>
                </tr>
            </table>
            <p><button class="button button-primary" type="submit"><?php esc_html_e('Save Settings', 'wp-genova-woocommerce'); ?></button></p>
        </form>
    </div>
    <?php
}

/**
 * Frontend: enqueue scripts & styles
 */
add_action('wp_enqueue_scripts', function () {
    if (!class_exists('WooCommerce')) return;

    if (is_checkout() || is_cart()) {
        wp_enqueue_script('wp-genova-js', WP_GENOVA_PLUGIN_URL . 'assets/wp-genova.js', ['jquery'], '1.1.0', true);
        wp_localize_script('wp-genova-js', 'WP_GENOVA', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_genova_nonce'),
        ]);
        wp_enqueue_style('wp-genova-css', WP_GENOVA_PLUGIN_URL . 'assets/wp-genova.css');
    }
});

// Checkout UI placeholder (inject into review order before submit)
add_action('woocommerce_review_order_before_submit', function () {
    echo '<div id="wp-genova-insurance" class="wp-genova-insurance">';
    echo '<h3>' . esc_html__('Genova Product Insurance', 'wp-genova-woocommerce') . '</h3>';
    echo '<p>' . esc_html__('Optional protection for your purchase. Select a plan below.', 'wp-genova-woocommerce') . '</p>';
    echo '<select id="wp-genova-plan" name="wp_genova_plan"><option value="">' . esc_html__('-- No insurance --', 'wp-genova-woocommerce') . '</option></select>';
    echo '<p id="wp-genova-selected" style="margin-top:.5rem"></p>';
    echo '</div>';
});

/**
 * AJAX: get plans from Genova API (server side to hide API key)
 */
add_action('wp_ajax_wp_genova_get_plans', 'wp_genova_get_plans');
add_action('wp_ajax_nopriv_wp_genova_get_plans', 'wp_genova_get_plans');
function wp_genova_get_plans() {
    check_ajax_referer('wp_genova_nonce', 'nonce');
    $opts = get_option(WP_GENOVA_SETTINGS, []);
    $api_base = rtrim($opts['api_base'] ?? '', '/');

    if (empty($api_base)) {
        wp_send_json_error(['message' => 'API base not configured'], 400);
    }

    $url = $api_base . '/plans';
    $args = ['timeout' => 12, 'headers' => ['Accept' => 'application/json']];
    $api_key_enc = $opts['api_key'] ?? '';
    $api_key = $api_key_enc ? wp_genova_decrypt($api_key_enc) : '';
    if (!empty($api_key)) $args['headers']['Authorization'] = 'Bearer ' . $api_key;

    $resp = wp_remote_get($url, $args);
    if (is_wp_error($resp)) return wp_send_json_error(['message' => $resp->get_error_message()], 500);
    $code = wp_remote_retrieve_response_code($resp);
    $body = wp_remote_retrieve_body($resp);
    if ($code < 200 || $code >= 300) return wp_send_json_error(['message' => 'Bad response from API', 'code' => $code], $code);
    $plans = json_decode($body, true);
    if (!is_array($plans)) return wp_send_json_error(['message' => 'Invalid JSON from API'], 500);
    wp_send_json_success(['plans' => $plans]);
}

/**
 * AJAX: set selected plan in session
 */
add_action('wp_ajax_wp_genova_set_plan', 'wp_genova_set_plan');
add_action('wp_ajax_nopriv_wp_genova_set_plan', 'wp_genova_set_plan');
function wp_genova_set_plan() {
    check_ajax_referer('wp_genova_nonce', 'nonce');
    $plan_id = isset($_POST['plan_id']) ? sanitize_text_field(wp_unslash($_POST['plan_id'])) : '';

    if (empty($plan_id)) {
        if (WC()->session) {
            WC()->session->__unset('wp_genova_plan');
            WC()->session->__unset('wp_genova_price');
        }
        if (function_exists('wc')) wc()->cart->calculate_totals();
        return wp_send_json_success(['message' => 'Plan cleared']);
    }

    // fetch plans server side and find selected plan price
    $opts = get_option(WP_GENOVA_SETTINGS, []);
    $api_base = rtrim($opts['api_base'] ?? '', '/');
    $url = $api_base . '/plans';
    $args = ['timeout' => 12, 'headers' => ['Accept' => 'application/json']];
    $api_key_enc = $opts['api_key'] ?? '';
    $api_key = $api_key_enc ? wp_genova_decrypt($api_key_enc) : '';
    if (!empty($api_key)) $args['headers']['Authorization'] = 'Bearer ' . $api_key;
    $resp = wp_remote_get($url, $args);
    if (is_wp_error($resp)) return wp_send_json_error(['message' => 'Failed fetching plans'], 500);
    $plans = json_decode(wp_remote_retrieve_body($resp), true);

    $selected = null;
    if (is_array($plans)) {
        foreach ($plans as $p) {
            if ((string)$p['id'] === (string)$plan_id) { $selected = $p; break; }
        }
    }
    if (!$selected) return wp_send_json_error(['message' => 'Plan not found'], 404);

    if (WC()->session) {
        WC()->session->set('wp_genova_plan', $selected['id']);
        WC()->session->set('wp_genova_price', floatval($selected['price'] ?? 0));
    }

    // Recalculate totals
    if (function_exists('wc')) wc()->cart->calculate_totals();

    wp_send_json_success(['plan' => $selected]);
}

/**
 * Add fee to cart based on session plan
 */
add_action('woocommerce_cart_calculate_fees', 'wp_genova_add_fee');
function wp_genova_add_fee($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (!WC()->session) return;
    $plan = WC()->session->get('wp_genova_plan');
    $price = WC()->session->get('wp_genova_price');
    if ($plan && $price && $price > 0) {
        $label = sprintf(__('Insurance (plan %s)', 'wp-genova-woocommerce'), esc_html($plan));
        $cart->add_fee($label, floatval($price), false);
    }
}

/**
 * Save plan on order meta
 */
add_action('woocommerce_checkout_update_order_meta', function ($order_id, $data = null) {
    if (WC()->session) {
        $plan = WC()->session->get('wp_genova_plan');
        if ($plan) update_post_meta($order_id, '_wp_genova_plan', sanitize_text_field($plan));
        $price = WC()->session->get('wp_genova_price');
        if ($price) update_post_meta($order_id, '_wp_genova_fee', floatval($price));
    }
}, 10, 2);

/**
 * Purchase insurance when order processed or payment complete based on settings
 * If purchase fails, schedule background retry using Action Scheduler.
 */
add_action('woocommerce_checkout_order_processed', 'wp_genova_purchase_on_order', 10, 3);
add_action('woocommerce_payment_complete', 'wp_genova_purchase_on_payment_complete', 10, 1);

function wp_genova_purchase_on_order($order_id, $posted_data, $order) {
    $opts = get_option(WP_GENOVA_SETTINGS, []);
    if (($opts['purchase_trigger'] ?? 'order_processed') !== 'order_processed') return;
    wp_genova_enqueue_purchase($order_id);
}
function wp_genova_purchase_on_payment_complete($order_id) {
    $opts = get_option(WP_GENOVA_SETTINGS, []);
    if (($opts['purchase_trigger'] ?? 'order_processed') !== 'payment_complete') return;
    wp_genova_enqueue_purchase($order_id);
}

function wp_genova_enqueue_purchase($order_id) {
    // Attempt immediate call synchronously; if it fails we'll schedule a retry
    $result = wp_genova_call_purchase($order_id);
    if ($result['success']) return true;

    // Schedule Action Scheduler retries (if available)
    $opts = get_option(WP_GENOVA_SETTINGS, []);
    $max = intval($opts['max_retries'] ?? 3);
    if (!class_exists('\ActionScheduler')) {
        // No Action Scheduler; log and store error
        update_post_meta($order_id, '_wp_genova_purchase_error', $result['message']);
        return false;
    }

    // schedule with exponential backoff: 1min, 5min, 30min, 2h... up to max
    $delay = 60; // seconds
    $handle = 'wp_genova_purchase_order_' . $order_id;
    // store retry count meta
    update_post_meta($order_id, '_wp_genova_retries', 0);

    // Schedule single action that will reschedule itself until max_retries
    as_schedule_single_action(time() + $delay, 'wp_genova_action_purchase_order', ['order_id' => $order_id], $handle);
    return false;
}

// Action Scheduler hook
add_action('wp_genova_action_purchase_order', 'wp_genova_action_purchase_handler');
function wp_genova_action_purchase_handler($args) {
    $order_id = $args['order_id'];
    $retries = intval(get_post_meta($order_id, '_wp_genova_retries', true) ?: 0);
    $opts = get_option(WP_GENOVA_SETTINGS, []);
    $max = intval($opts['max_retries'] ?? 3);

    $result = wp_genova_call_purchase($order_id);
    if ($result['success']) {
        // success - clear retry counter
        delete_post_meta($order_id, '_wp_genova_retries');
        return true;
    }

    $retries++;
    update_post_meta($order_id, '_wp_genova_retries', $retries);

    if ($retries >= $max) {
        // give up and log
        update_post_meta($order_id, '_wp_genova_purchase_error', 'Max retries reached: ' . $result['message']);
        // log to WooCommerce logger if available
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $context = ['source' => 'wp-genova'];
            $logger->error('Purchase failed after max retries for order ' . $order_id . ': ' . $result['message'], $context);
        } else {
            error_log('WP Genova purchase failed: ' . $result['message']);
        }
        return false;
    }

    // schedule next attempt with exponential backoff
    $backoffs = [60, 300, 1800, 7200];
    $idx = min($retries - 1, count($backoffs) - 1);
    $delay = $backoffs[$idx];
    as_schedule_single_action(time() + $delay, 'wp_genova_action_purchase_order', ['order_id' => $order_id], 'wp_genova_purchase_order_'.$order_id.'_retry_'.$retries);
    return false;
}

/**
 * Performs the HTTP call to the Genova purchase endpoint.
 * Returns ['success' => bool, 'message' => string, 'data' => array]
 */
function wp_genova_call_purchase($order_id) {
    $plan_id = get_post_meta($order_id, '_wp_genova_plan', true);
    if (!$plan_id) return ['success' => false, 'message' => 'No plan selected'];

    $order = wc_get_order($order_id);
    if (!$order) return ['success' => false, 'message' => 'Order not found'];

    $opts = get_option(WP_GENOVA_SETTINGS, []);
    $api_base = rtrim($opts['api_base'] ?? '', '/');
    if (empty($api_base)) return ['success' => false, 'message' => 'API base not configured'];

    $payload = [
        'order_id' => $order_id,
        'plan_id' => $plan_id,
        'buyer' => [
            'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
        ],
        'items' => [],
        'order_total' => floatval($order->get_total()),
    ];
    foreach ($order->get_items() as $item) {
        $prod = $item->get_product();
        $payload['items'][] = [
            'sku' => $prod ? $prod->get_sku() : '',
            'name' => $item->get_name(),
            'qty' => intval($item->get_quantity()),
            'price' => floatval($item->get_total()),
        ];
    }

    $args = [
        'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
        'body' => wp_json_encode($payload),
        'timeout' => 20,
    ];

    $api_key_enc = $opts['api_key'] ?? '';
    $api_key = $api_key_enc ? wp_genova_decrypt($api_key_enc) : '';
    if (!empty($api_key)) $args['headers']['Authorization'] = 'Bearer ' . $api_key;

    $resp = wp_remote_post($api_base . '/purchase', $args);
    if (is_wp_error($resp)) return ['success' => false, 'message' => $resp->get_error_message()];

    $code = wp_remote_retrieve_response_code($resp);
    $body = wp_remote_retrieve_body($resp);

    if ($code < 200 || $code >= 300) return ['success' => false, 'message' => "HTTP {$code}: " . substr($body, 0, 500)];

    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['policy_id'])) return ['success' => false, 'message' => 'Invalid response body'];

    // Save policy details
    update_post_meta($order_id, '_wp_genova_policy_id', sanitize_text_field($data['policy_id']));
    update_post_meta($order_id, '_wp_genova_policy_raw', wp_slash($body));

    // log success
    if (function_exists('wc_get_logger')) {
        $logger = wc_get_logger();
        $context = ['source' => 'wp-genova'];
        $logger->info('Purchased insurance for order ' . $order_id . ' policy ' . $data['policy_id'], $context);
    }

    return ['success' => true, 'message' => 'OK', 'data' => $data];
}

/**
 * Claim form shortcode
 */
add_shortcode('wp_genova_claim_form', function () {
    ob_start();
    if (isset($_POST['wp_genova_claim_nonce']) && wp_verify_nonce($_POST['wp_genova_claim_nonce'], 'wp_genova_claim')) {
        $policy_id = sanitize_text_field($_POST['policy_id'] ?? '');
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');
        if ($policy_id && $reason) {
            $opts = get_option(WP_GENOVA_SETTINGS, []);
            $api_base = rtrim($opts['api_base'] ?? '', '/');
            $args = ['headers' => ['Content-Type' => 'application/json'], 'body' => wp_json_encode(['policy_id' => $policy_id, 'reason' => $reason]), 'timeout' => 20];
            $api_key_enc = $opts['api_key'] ?? '';
            $api_key = $api_key_enc ? wp_genova_decrypt($api_key_enc) : '';
            if (!empty($api_key)) $args['headers']['Authorization'] = 'Bearer ' . $api_key;
            $resp = wp_remote_post($api_base . '/claim', $args);
            if (is_wp_error($resp)) {
                echo '<p>' . esc_html__('Failed to submit claim. Try again later.', 'wp-genova-woocommerce') . '</p>';
            } else {
                $code = wp_remote_retrieve_response_code($resp);
                $body = wp_remote_retrieve_body($resp);
                if ($code >= 200 && $code < 300) {
                    $data = json_decode($body, true);
                    echo '<p>' . esc_html__('Claim submitted successfully.', 'wp-genova-woocommerce') . '</p>';
                    if (!empty($data['claim_id'])) echo '<p>' . esc_html__('Claim ID: ', 'wp-genova-woocommerce') . esc_html($data['claim_id']) . '</p>';
                } else {
                    echo '<p>' . esc_html__('Failed to submit claim. Provider returned an error.', 'wp-genova-woocommerce') . '</p>';
                }
            }
        } else {
            echo '<p>' . esc_html__('Please supply a policy ID and reason.', 'wp-genova-woocommerce') . '</p>';
        }
    }
    ?>
    <form method="post" class="wp-genova-claim-form">
        <?php wp_nonce_field('wp_genova_claim', 'wp_genova_claim_nonce'); ?>
        <p>
            <label><?php esc_html_e('Policy ID', 'wp-genova-woocommerce'); ?></label><br>
            <input type="text" name="policy_id" required style="width:100%" />
        </p>
        <p>
            <label><?php esc_html_e('Reason for claim', 'wp-genova-woocommerce'); ?></label><br>
            <textarea name="reason" rows="6" style="width:100%" required></textarea>
        </p>
        <p><button type="submit" class="button button-primary"><?php esc_html_e('Submit claim', 'wp-genova-woocommerce'); ?></button></p>
    </form>
    <?php
    return ob_get_clean();
});

/**
 * Admin columns to show policy id and purchase errors
 */
add_filter('manage_edit-shop_order_columns', function ($columns) {
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ($key === 'order_total') {
            $new['wp_genova_policy'] = __('Policy ID', 'wp-genova-woocommerce');
            $new['wp_genova_error'] = __('Genova Error', 'wp-genova-woocommerce');
        }
    }
    return $new;
});
add_action('manage_shop_order_posts_custom_column', function ($column) {
    global $post;
    if ($column === 'wp_genova_policy') {
        $policy = get_post_meta($post->ID, '_wp_genova_policy_id', true);
        if ($policy) echo esc_html($policy);
        else echo '<span class="na">-</span>';
    }
    if ($column === 'wp_genova_error') {
        $err = get_post_meta($post->ID, '_wp_genova_purchase_error', true);
        if ($err) echo esc_html($err);
        else echo '<span class="na">-</span>';
    }
});

/* End main plugin file */