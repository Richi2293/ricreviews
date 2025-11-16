<?php
/**
 * Uninstall script for RicReviews
 *
 * @package RicReviews
 */

// Exit if uninstall not called from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Include database class
require_once plugin_dir_path(__FILE__) . 'includes/class-ricreviews-database.php';

// Remove all options
$options = array(
    'ricreviews_api_key',
    'ricreviews_place_id',
    'ricreviews_limit',
    'ricreviews_order_by',
    'ricreviews_order',
    'ricreviews_primary_color',
    'ricreviews_theme',
);

foreach ($options as $option) {
    delete_option($option);
}

// Clear all transients
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} 
        WHERE option_name LIKE %s 
        OR option_name LIKE %s",
        '_transient_ricreviews_%',
        '_transient_timeout_ricreviews_%'
    )
);

// Remove scheduled cron events
$timestamp = wp_next_scheduled('ricreviews_daily_fetch');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'ricreviews_daily_fetch');
}

// Drop database table
$database = new RicReviews_Database();
$database->drop_table();

