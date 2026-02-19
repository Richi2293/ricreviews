<?php
/**
 * Database operations for RicReviews
 *
 * @package RicReviews
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RicReviews_Database
 */
class RicReviews_Database
{

    /**
     * Table name
     *
     * @var string
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ricreviews_reviews';
    }

    /**
     * Get table name
     *
     * @return string
     */
    public function get_table_name()
    {
        return $this->table_name;
    }

    /**
     * Create database table
     *
     * @return void
     */
    public function create_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            place_id VARCHAR(255) NOT NULL,
            review_id VARCHAR(255) NOT NULL,
            author_name VARCHAR(255) NOT NULL,
            author_url TEXT,
            profile_photo_url TEXT,
            rating TINYINT(1) UNSIGNED NOT NULL,
            text TEXT,
            original_text TEXT,
            time INT(11) UNSIGNED NOT NULL,
            relative_time_description VARCHAR(100),
            language VARCHAR(10),
            original_language VARCHAR(10),
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY review_id (review_id),
            KEY place_id (place_id),
            KEY rating (rating),
            KEY time (time),
            KEY language (language)
        ) ENGINE=InnoDB {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Save reviews to database (upsert)
     *
     * @param string $place_id Google Place ID
     * @param array  $reviews  Array of review data
     * @return bool|WP_Error
     */
    public function save_reviews($place_id, $reviews)
    {
        global $wpdb;

        if (empty($reviews) || !is_array($reviews)) {
            return new WP_Error('invalid_reviews', __('Invalid reviews data provided.', 'ricreviews'));
        }

        $saved_count = 0;

        foreach ($reviews as $review) {
            // Generate review_id if not present (use author_name + time as fallback)
            $review_id = isset($review['review_id']) ? $review['review_id'] : md5($place_id . $review['author_name'] . $review['time']);

            // Prepare data
            $data = array(
                'place_id' => sanitize_text_field($place_id),
                'review_id' => sanitize_text_field($review_id),
                'author_name' => sanitize_text_field($review['author_name']),
                'author_url' => isset($review['author_url']) ? esc_url_raw($review['author_url']) : null,
                'profile_photo_url' => isset($review['profile_photo_url']) ? esc_url_raw($review['profile_photo_url']) : null,
                'rating' => absint($review['rating']),
                'text' => isset($review['text']) ? wp_kses_post($review['text']) : null,
                'original_text' => isset($review['original_text']) ? wp_kses_post($review['original_text']) : null,
                'time' => absint($review['time']),
                'relative_time_description' => isset($review['relative_time_description']) ? sanitize_text_field($review['relative_time_description']) : null,
                'language' => isset($review['language']) ? sanitize_text_field($review['language']) : null,
                'original_language' => isset($review['original_language']) ? sanitize_text_field($review['original_language']) : null,
            );

            // Check if review already exists
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; caching handled by RicReviews_Cache layer.
            $existing = $wpdb->get_var($wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated internally by the plugin (not user input).
                "SELECT id FROM {$this->table_name} WHERE review_id = %s",
                $review_id
            ));

            if ($existing) {
                // Update existing review
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; caching handled by RicReviews_Cache layer.
                $result = $wpdb->update(
                    $this->table_name,
                    $data,
                    array('review_id' => $review_id),
                    array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s'),
                    array('%s')
                );
            } else {
                // Insert new review
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; caching handled by RicReviews_Cache layer.
                $result = $wpdb->insert(
                    $this->table_name,
                    $data,
                    array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s')
                );
            }

            if ($result !== false) {
                $saved_count++;
            }
        }

        return $saved_count > 0;
    }

    /**
     * Get reviews from database
     *
     * @param string $place_id Google Place ID
     * @param int    $limit    Number of reviews to retrieve
     * @param string $order_by Order by field (time, rating, or created_at)
     * @param string $order    Order direction (ASC or DESC)
     * @param string $language Optional language code to filter reviews (e.g., 'it', 'en')
     * @return array
     */
    public function get_reviews($place_id, $limit = 10, $order_by = 'time', $order = 'DESC', $language = '')
    {
        global $wpdb;

        // Handle time_asc special case
        if ($order_by === 'time_asc') {
            $order_by = 'time';
            $order = 'ASC';
        }

        // Validate order_by
        $allowed_order_by = array('time', 'rating', 'created_at');
        if (!in_array($order_by, $allowed_order_by, true)) {
            $order_by = 'time';
        }

        // Validate order
        $order = strtoupper($order);
        if ($order !== 'ASC' && $order !== 'DESC') {
            $order = 'DESC';
        }

        // Validate limit
        $limit = absint($limit);
        if ($limit <= 0) {
            $limit = 10;
        }

        // Sanitize language code
        $language = !empty($language) ? sanitize_text_field($language) : '';

        // Build safe query using fixed templates and placeholders only.
        // - $this->table_name is set internally in __construct(), never from user input.
        // - $order_by and $order are whitelisted to known values above.
        $has_language = !empty($language);

        if ($order_by === 'rating') {
            $order_by_sql = 'rating';
        } else {
            $order_by_sql = 'time';
        }

        if ($order === 'ASC') {
            $order_sql = 'ASC';
        } else {
            $order_sql = 'DESC';
        }

        $table_name = esc_sql($this->table_name);

        if ($has_language) {
            if ($order_by_sql === 'rating' && $order_sql === 'ASC') {
                $sql = $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE place_id = %s AND language = %s ORDER BY rating ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and sanitized.
                    array($place_id, $language, $limit)
                );
            } elseif ($order_by_sql === 'rating' && $order_sql === 'DESC') {
                $sql = $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE place_id = %s AND language = %s ORDER BY rating DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and sanitized.
                    array($place_id, $language, $limit)
                );
            } elseif ($order_by_sql === 'time' && $order_sql === 'ASC') {
                $sql = $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE place_id = %s AND language = %s ORDER BY time ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and sanitized.
                    array($place_id, $language, $limit)
                );
            } else {
                $sql = $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE place_id = %s AND language = %s ORDER BY time DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and sanitized.
                    array($place_id, $language, $limit)
                );
            }
        } else {
            if ($order_by_sql === 'rating' && $order_sql === 'ASC') {
                $sql = $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE place_id = %s ORDER BY rating ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and sanitized.
                    array($place_id, $limit)
                );
            } elseif ($order_by_sql === 'rating' && $order_sql === 'DESC') {
                $sql = $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE place_id = %s ORDER BY rating DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and sanitized.
                    array($place_id, $limit)
                );
            } elseif ($order_by_sql === 'time' && $order_sql === 'ASC') {
                $sql = $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE place_id = %s ORDER BY time ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and sanitized.
                    array($place_id, $limit)
                );
            } else {
                $sql = $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE place_id = %s ORDER BY time DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and sanitized.
                    array($place_id, $limit)
                );
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin table; caching in RicReviews_Cache; $sql is built with $wpdb->prepare() above; all variables are whitelisted or prepared.
        $results = $wpdb->get_results($sql, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (empty($results)) {
            return array();
        }

        return $results;
    }

    /**
     * Delete reviews by place_id
     *
     * @param string $place_id Google Place ID
     * @return bool|int Number of rows deleted or false on error
     */
    public function delete_reviews_by_place_id($place_id)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; this is a deliberate destructive operation with no caching needed.
        return $wpdb->delete(
            $this->table_name,
            array('place_id' => $place_id),
            array('%s')
        );
    }

    /**
     * Drop database table
     *
     * @return bool
     */
    public function drop_table()
    {
        global $wpdb;

        // DROP TABLE cannot use prepare(); table name is set internally and never derived from user input.
        $sql = "DROP TABLE IF EXISTS {$this->table_name}"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->query($sql) !== false; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }
}
