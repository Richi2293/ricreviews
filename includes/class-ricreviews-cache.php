<?php
/**
 * Cache management for RicReviews
 *
 * @package RicReviews
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RicReviews_Cache
 */
class RicReviews_Cache
{

    /**
     * Cache duration in seconds (24 hours)
     *
     * @var int
     */
    private $cache_duration = 86400;

    /**
     * Get cached reviews
     *
     * @param string $place_id Google Place ID
     * @return array|false Array of reviews or false if not cached
     */
    public function get_cached_reviews($place_id)
    {
        $cache_key = $this->get_cache_key($place_id);
        return get_transient($cache_key);
    }

    /**
     * Set cached reviews
     *
     * @param string $place_id Google Place ID
     * @param array  $reviews   Array of reviews to cache
     * @return bool
     */
    public function set_cached_reviews($place_id, $reviews)
    {
        if (empty($place_id) || !is_array($reviews)) {
            return false;
        }

        $cache_key = $this->get_cache_key($place_id);
        return set_transient($cache_key, $reviews, $this->cache_duration);
    }

    /**
     * Clear cache for a specific place
     *
     * @param string $place_id Google Place ID
     * @return bool
     */
    public function clear_cache($place_id = '')
    {
        if (empty($place_id)) {
            // Clear all ricreviews transients
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Necessary to bulk-delete all plugin transients; no native WP API handles this efficiently.
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} 
                    WHERE option_name LIKE %s 
                    OR option_name LIKE %s",
                    '_transient_ricreviews_%',
                    '_transient_timeout_ricreviews_%'
                )
            );
            return true;
        }

        $cache_key = $this->get_cache_key($place_id);
        return delete_transient($cache_key);
    }

    /**
     * Get cache key for a place
     *
     * @param string $place_id Google Place ID
     * @return string
     */
    private function get_cache_key($place_id)
    {
        return 'ricreviews_' . md5($place_id);
    }

    /**
     * Get cache duration
     *
     * @return int
     */
    public function get_cache_duration()
    {
        return $this->cache_duration;
    }

    /**
     * Set cache duration
     *
     * @param int $duration Duration in seconds
     * @return void
     */
    public function set_cache_duration($duration)
    {
        $this->cache_duration = absint($duration);
    }
}

