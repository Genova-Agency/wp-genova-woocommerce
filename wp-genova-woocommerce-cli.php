<?php
/**
 * Author:      Evans Wanguba
 * Text Domain: wp-genova-woocommerce-cli
 */

if (defined('WP_CLI') && WP_CLI) {
    class WP_Genova_CLI_Command {
    /**
    * Retry failed purchases for recent orders.
    *
    * ## OPTIONS
    *
    * [--limit=<limit>]
    * : Number of orders to process (default 50)
    *
    * ## EXAMPLES
    *
    * wp genova retry --limit=20
    */
    public function retry($args, $assoc) {
    $limit = isset($assoc['limit']) ? intval($assoc['limit']) : 50;
    $query = new WP_Query([ 'post_type' => 'shop_order', 'posts_per_page' => $limit, 'meta_query' => [ ['key' => '_wp_genova_purchase_error', 'compare' => 'EXISTS'] ] ]);
    $count = 0;
    foreach ($query->posts as $p) {
    WP_CLI::log('Enqueue purchase for order ' . $p->ID);
    wp_genova_enqueue_purchase($p->ID);
    $count++;
    }
    WP_CLI::success("Enqueued {$count} orders for retry.");
    }
    }
    WP_CLI::add_command('genova', 'WP_Genova_CLI_Command');
}