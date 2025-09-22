<?php
if (!defined('ABSPATH')) exit;

class WP_Genova_Checkout {
    public function __construct() {
        add_action('woocommerce_after_order_notes', [$this, 'add_insurance_field']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_insurance_choice']);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_insurance_in_admin']);
    }

    public function add_insurance_field($checkout) {
        woocommerce_form_field('genova_insurance', [
            'type' => 'checkbox',
            'class' => ['form-row-wide'],
            'label' => __('Add Genova Insurance (+$10)', 'wp-genova'),
        ], $checkout->get_value('genova_insurance'));
    }

    public function save_insurance_choice($order_id) {
        if (!empty($_POST['genova_insurance'])) {
            update_post_meta($order_id, '_genova_insurance', 'yes');
        }
    }

    public function display_insurance_in_admin($order) {
        $insurance = get_post_meta($order->get_id(), '_genova_insurance', true);
        if ($insurance === 'yes') {
            echo '<p><strong>' . __('Genova Insurance:', 'wp-genova') . '</strong> Yes</p>';
        }
    }
}
