<?php
if (!defined('ABSPATH')) exit;

class WP_Genova_Settings {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_settings_page() {
        add_options_page(
            'Genova Insurance',
            'Genova Insurance',
            'manage_options',
            'wp-genova-settings',
            [$this, 'settings_page_html']
        );
    }

    public function register_settings() {
        register_setting('wp_genova_settings', 'wp_genova_api_base');
        register_setting('wp_genova_settings', 'wp_genova_api_key');
    }

    public function settings_page_html() {
        ?>
        <div class="wrap">
            <h1>Genova Insurance Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('wp_genova_settings'); ?>
                <?php do_settings_sections('wp_genova_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">API Base URL</th>
                        <td><input type="text" name="wp_genova_api_base" value="<?php echo esc_attr(get_option('wp_genova_api_base')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">API Key</th>
                        <td><input type="text" name="wp_genova_api_key" value="<?php echo esc_attr(get_option('wp_genova_api_key')); ?>" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
