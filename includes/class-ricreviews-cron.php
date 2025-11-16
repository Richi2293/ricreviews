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
class RicReviews_Cron {
    
    /**
     * Cron hook name
     *
     * @var string
     */
    private $cron_hook = 'ricreviews_daily_fetch';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action($this->cron_hook, array($this, 'fetch_and_update_reviews'));
    }
    
    /**
     * Schedule cron event
     *
     * @return void
     */
    public function schedule_event() {
        if (!wp_next_scheduled($this->cron_hook)) {
            wp_schedule_event(time(), 'daily', $this->cron_hook);
        }
    }
    
    /**
     * Unschedule cron event
     *
     * @return void
     */
    public function unschedule_event() {
        $timestamp = wp_next_scheduled($this->cron_hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $this->cron_hook);
        }
    }
    
    /**
     * Fetch and update reviews (called by cron)
     *
     * @return void
     */
    public function fetch_and_update_reviews() {
        $api_key = get_option('ricreviews_api_key');
        $place_id = get_option('ricreviews_place_id');
        
        if (empty($api_key) || empty($place_id)) {
            return;
        }
        
        $api = new RicReviews_API();
        $reviews = $api->fetch_reviews($place_id, $api_key);
        
        if (is_wp_error($reviews)) {
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
        }
    }
}

