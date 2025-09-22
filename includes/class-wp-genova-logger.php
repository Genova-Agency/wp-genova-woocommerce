<?php
if (!defined('ABSPATH')) exit;

class WP_Genova_Logger {
    public static function init() {
        add_action('genova_log_event', [__CLASS__, 'log_event'], 10, 2);
    }

    public static function log_event($context, $message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[Genova][$context] $message");
        }
    }
}
