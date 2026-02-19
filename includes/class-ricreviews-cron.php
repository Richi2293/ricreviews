<?php
/**
 * WordPress Cron for RicReviews
 *
 * @package RicReviews
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RicReviews_Cron
 */
class RicReviews_Cron
{

    /**
     * Cron hook name
     *
     * @var string
     */
    private $cron_hook = 'ricreviews_daily_fetch';

    /**
     * Constructor
     */
    public function __construct()
    {
        add_filter('cron_schedules', array($this, 'add_custom_cron_schedules'));
        add_action($this->cron_hook, array($this, 'fetch_and_update_reviews'));
    }

    /**
     * Add custom cron schedules
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function add_custom_cron_schedules($schedules)
    {
        $schedules['weekly'] = array(
            'interval' => 604800, // 7 * 24 * 60 * 60
            'display' => __('Weekly', 'ricreviews')
        );

        $schedules['monthly'] = array(
            'interval' => 2592000, // 30 * 24 * 60 * 60 (approximate)
            'display' => __('Monthly', 'ricreviews')
        );

        return $schedules;
    }

    /**
     * Schedule cron event
     *
     * @return void
     */
    public function schedule_event()
    {
        $enabled = get_option('ricreviews_cron_enabled', 'yes');

        // If disabled, unschedule and return
        if ($enabled !== 'yes') {
            $this->unschedule_event();
            return;
        }

        $frequency = get_option('ricreviews_cron_frequency', 'daily');

        // Check if already scheduled
        $timestamp = wp_next_scheduled($this->cron_hook);

        if ($timestamp) {
            // Check if the frequency matches
            $schedule = wp_get_schedule($this->cron_hook);
            if ($schedule === $frequency) {
                return; // Already scheduled correctly
            }

            // If frequency changed, unschedule first
            wp_unschedule_event($timestamp, $this->cron_hook);
        }

        // Schedule new event
        wp_schedule_event(time(), $frequency, $this->cron_hook);
    }

    /**
     * Unschedule cron event
     *
     * @return void
     */
    public function unschedule_event()
    {
        $timestamp = wp_next_scheduled($this->cron_hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $this->cron_hook);
        }
    }

    /**
     * Reschedule event (helper for admin settings)
     * 
     * @return void
     */
    public function reschedule_event()
    {
        $this->unschedule_event();
        $this->schedule_event();
    }

    /**
     * Fetch and update reviews (called by cron)
     *
     * @return void
     */
    public function fetch_and_update_reviews()
    {
        $api_key = get_option('ricreviews_api_key');
        $place_id = get_option('ricreviews_place_id');

        if (empty($api_key) || empty($place_id)) {
            return;
        }

        $api = new RicReviews_API();

        // Get languages configuration
        $languages_config = get_option('ricreviews_languages', '');
        $languages_array = array();

        if (!empty($languages_config)) {
            // Parse comma-separated languages
            $languages_array = array_map('trim', explode(',', $languages_config));
            $languages_array = array_filter($languages_array);
        }

        // If multiple languages configured, use multiple language fetch
        if (!empty($languages_array)) {
            $reviews = $api->fetch_reviews_multiple_languages($place_id, $api_key, $languages_array);
        } else {
            // Single language fetch (uses WordPress locale)
            $reviews = $api->fetch_reviews($place_id, $api_key);
        }

        if (is_wp_error($reviews)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Necessary to report unexpected cron job failures to the server error log for site administrators.
            error_log('RicReviews Cron Error: ' . $reviews->get_error_message());
            return;
        }

        if (!empty($reviews)) {
            // Save to database
            $database = new RicReviews_Database();
            $database->save_reviews($place_id, $reviews);

            // Update cache
            $cache = new RicReviews_Cache();
            $cache->set_cached_reviews($place_id, $reviews);

            // Update last fetch timestamp
            update_option('ricreviews_last_fetch', current_time('mysql'));
        }
    }
}

