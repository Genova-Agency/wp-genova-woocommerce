<?php
if (!defined('ABSPATH')) exit;

class WP_Genova_Claims {
    public function __construct() {
        add_shortcode('wp_genova_claim_form', [$this, 'render_claim_form']);
        add_action('init', [$this, 'handle_claim_submission']);
    }

    public function render_claim_form() {
        ob_start();
        ?>
        <form method="post">
            <h3>Submit Insurance Claim</h3>
            <p><label>Order ID <input type="text" name="claim_order_id" required></label></p>
            <p><label>Description <textarea name="claim_description" required></textarea></label></p>
            <input type="hidden" name="genova_claim_nonce" value="<?php echo wp_create_nonce('genova_claim'); ?>">
            <p><button type="submit" name="submit_genova_claim">Submit Claim</button></p>
        </form>
        <?php
        return ob_get_clean();
    }

    public function handle_claim_submission() {
        if (isset($_POST['submit_genova_claim']) && wp_verify_nonce($_POST['genova_claim_nonce'], 'genova_claim')) {
            $order_id = sanitize_text_field($_POST['claim_order_id']);
            $desc = sanitize_textarea_field($_POST['claim_description']);
            // TODO: send to API
            wp_mail(get_option('admin_email'), "New Genova Claim", "Order: $order_id\n\n$desc");
            wp_redirect(add_query_arg('claim_submitted', '1', wp_get_referer()));
            exit;
        }
    }
}
