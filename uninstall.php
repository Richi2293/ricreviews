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
$ricreviews_options = array(
    'ricreviews_api_key',
    'ricreviews_place_id',
    'ricreviews_limit',
    'ricreviews_order_by',
    'ricreviews_order',
    'ricreviews_primary_color',
    'ricreviews_theme',
    'ricreviews_languages',
    'ricreviews_last_fetch',
    'ricreviews_debug_logging',
    'ricreviews_cron_enabled',
    'ricreviews_cron_frequency',
);

foreach ($ricreviews_options as $ricreviews_option) {
    delete_option($ricreviews_option);
}

// Clear all transients
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Necessary to bulk-delete plugin transients; no WP API can do this efficiently.
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
$ricreviews_timestamp = wp_next_scheduled('ricreviews_daily_fetch');
if ($ricreviews_timestamp) {
    wp_unschedule_event($ricreviews_timestamp, 'ricreviews_daily_fetch');
}

// Drop database table
$ricreviews_database = new RicReviews_Database();
$ricreviews_database->drop_table();

