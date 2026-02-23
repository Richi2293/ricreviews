<?php
/**
 * Shortcode handler for RicReviews
 *
 * @package RicReviews
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RicReviews_Shortcode
 */
class RicReviews_Shortcode
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_shortcode('ricreviews', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_styles'));
        add_action('wp_footer', array($this, 'maybe_enqueue_styles_footer'));
    }

    /**
     * Render shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_shortcode($atts)
    {
        // Parse attributes
        $atts = shortcode_atts(array(
            'limit' => get_option('ricreviews_limit', 10),
            'order_by' => get_option('ricreviews_order_by', 'time'),
            'order' => get_option('ricreviews_order', 'DESC'),
            'language' => '', // Empty means use WordPress locale
        ), $atts, 'ricreviews');

        $place_id = get_option('ricreviews_place_id');

        if (empty($place_id)) {
            return '<p>' . esc_html__('Please configure RicReviews settings in WordPress admin.', 'ricreviews') . '</p>';
        }

        // Get language code - if not specified, use WordPress locale
        $language = '';
        if (!empty($atts['language'])) {
            $language = sanitize_text_field($atts['language']);
        } else {
            // Convert WordPress locale to language code (e.g., 'it_IT' -> 'it')
            $locale = get_locale();
            if (!empty($locale)) {
                $lang_parts = explode('_', $locale);
                if (!empty($lang_parts[0])) {
                    $language = strtolower($lang_parts[0]);
                }
            }
        }

        // Get reviews
        $reviews = $this->get_reviews($place_id, absint($atts['limit']), $atts['order_by'], $atts['order'], $language);

        if (empty($reviews)) {
            return '<p>' . esc_html__('No reviews available at this time.', 'ricreviews') . '</p>';
        }

        // Mark that shortcode is used (for CSS enqueue)
        $this->set_shortcode_used();

        // Render reviews
        return $this->render_reviews($reviews);
    }

    /**
     * Get reviews (from cache, database, or API)
     *
     * @param string $place_id Google Place ID
     * @param int    $limit    Number of reviews
     * @param string $order_by Order by field
     * @param string $order    Order direction
     * @param string $language Optional language code to filter reviews
     * @return array
     */
    private function get_reviews($place_id, $limit, $order_by, $order, $language = '')
    {
        // Try database first (with language filter if specified)
        $database = new RicReviews_Database();
        $db_reviews = $database->get_reviews($place_id, $limit, $order_by, $order, $language);

        if (!empty($db_reviews)) {
            return $db_reviews;
        }

        // Try cache (but filter by language if specified)
        $cache = new RicReviews_Cache();
        $cached_reviews = $cache->get_cached_reviews($place_id);

        if (!empty($cached_reviews)) {
            // Filter cached reviews by language if specified
            if (!empty($language)) {
                $cached_reviews = array_filter($cached_reviews, function ($review) use ($language) {
                    return isset($review['language']) && $review['language'] === $language;
                });
            }

            if (!empty($cached_reviews)) {
                return $this->apply_sorting_and_limit($cached_reviews, $limit, $order_by, $order);
            }
        }

        // Fallback to API fetch
        $api_key = get_option('ricreviews_api_key');
        if (empty($api_key)) {
            return array();
        }

        $api = new RicReviews_API();

        // Always use single language fetch in shortcode
        // If language is specified, use it; otherwise use WordPress locale
        $reviews = $api->fetch_reviews($place_id, $api_key, $language);

        if (is_wp_error($reviews) || empty($reviews)) {
            return array();
        }

        // Save to database and cache
        $database->save_reviews($place_id, $reviews);
        $cache->set_cached_reviews($place_id, $reviews);

        // Filter by language if specified in shortcode
        if (!empty($language)) {
            $reviews = array_filter($reviews, function ($review) use ($language) {
                return isset($review['language']) && $review['language'] === $language;
            });
        }

        return $this->apply_sorting_and_limit($reviews, $limit, $order_by, $order);
    }

    /**
     * Apply sorting and limit to reviews
     *
     * @param array  $reviews  Reviews array
     * @param int    $limit    Number of reviews
     * @param string $order_by Order by field
     * @param string $order    Order direction
     * @return array
     */
    private function apply_sorting_and_limit($reviews, $limit, $order_by, $order)
    {
        if (empty($reviews)) {
            return array();
        }

        // Handle time_asc special case
        if ($order_by === 'time_asc') {
            $order_by = 'time';
            $order = 'ASC';
        }

        // Sort reviews
        usort($reviews, function ($a, $b) use ($order_by, $order) {
            $a_val = isset($a[$order_by]) ? $a[$order_by] : 0;
            $b_val = isset($b[$order_by]) ? $b[$order_by] : 0;

            if ($order === 'ASC') {
                return $a_val <=> $b_val;
            }
            return $b_val <=> $a_val;
        });

        // Limit reviews
        return array_slice($reviews, 0, $limit);
    }

    /**
     * Render reviews HTML
     *
     * @param array $reviews Reviews array
     * @return string
     */
    private function render_reviews($reviews)
    {
        $primary_color = get_option('ricreviews_primary_color', '#0073aa');
        $theme = get_option('ricreviews_theme', 'light');
        $place_id = get_option('ricreviews_place_id');

        ob_start();
        ?>
        <div class="ricreviews-container ricreviews-theme-<?php echo esc_attr($theme); ?>"
            style="--ricreviews-primary-color: <?php echo esc_attr($primary_color); ?>">
            <?php echo wp_kses_post($this->render_review_invite_panel($place_id)); ?>
            <div class="ricreviews-list">
                <?php foreach ($reviews as $review): ?>
                    <div class="ricreviews-item">
                        <div class="ricreviews-item__header">
                            <?php if (!empty($review['profile_photo_url'])): ?>
                                <div class="ricreviews-item__avatar">
                                    <img
                                        src="<?php echo esc_url($review['profile_photo_url']); ?>"
                                        alt="<?php echo esc_attr($review['author_name']); ?>"
                                        loading="lazy"
                                        decoding="async"
                                        referrerpolicy="no-referrer"
                                    />
                                </div>
                            <?php endif; ?>

                            <div class="ricreviews-item__author">
                                <div class="ricreviews-item__name">
                                    <?php if (!empty($review['author_url'])): ?>
                                        <a href="<?php echo esc_url($review['author_url']); ?>" target="_blank"
                                            rel="noopener noreferrer">
                                            <?php echo esc_html($review['author_name']); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo esc_html($review['author_name']); ?>
                                    <?php endif; ?>
                                </div>

                                <div class="ricreviews-item__rating">
                                    <?php echo wp_kses_post($this->render_stars($review['rating'])); ?>
                                    <?php if (!empty($review['relative_time_description'])): ?>
                                        <span class="ricreviews-item__time">
                                            <?php echo esc_html($review['relative_time_description']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($review['text'])): ?>
                            <div class="ricreviews-item__text">
                                <?php echo wp_kses_post($review['text']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render star rating
     *
     * @param int $rating Rating value (1-5)
     * @return string
     */
    private function render_stars($rating)
    {
        $rating = absint($rating);
        if ($rating < 1 || $rating > 5) {
            return '';
        }

        $output = '<div class="ricreviews-stars">';
        for ($i = 1; $i <= 5; $i++) {
            $class = $i <= $rating ? 'ricreviews-star--filled' : 'ricreviews-star--empty';
            $output .= '<span class="ricreviews-star ' . esc_attr($class) . '">★</span>';
        }
        $output .= '</div>';

        return $output;
    }

    /**
     * Render review invite panel
     *
     * @param string $place_id Google Place ID
     * @return string
     */
    private function render_review_invite_panel($place_id)
    {
        if (empty($place_id)) {
            return '';
        }

        // Generate Google Maps review URL
        $review_url = 'https://search.google.com/local/writereview?placeid=' . urlencode($place_id);

        ob_start();
        ?>
        <div class="ricreviews-invite-panel">
            <div class="ricreviews-invite-panel__content">
                <div class="ricreviews-invite-panel__message">
                    <?php echo esc_html__('Have you visited this place? Leave a review!', 'ricreviews'); ?>
                </div>
                <a href="<?php echo esc_url($review_url); ?>" target="_blank" rel="noopener noreferrer"
                    class="ricreviews-invite-panel__button">
                    <?php echo esc_html__('Write a Review', 'ricreviews'); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Set flag that shortcode is used (for CSS enqueue)
     *
     * @return void
     */
    private function set_shortcode_used()
    {
        global $ricreviews_shortcode_used;
        $ricreviews_shortcode_used = true;
    }

    /**
     * Maybe enqueue styles if shortcode is used (early check)
     *
     * @return void
     */
    public function maybe_enqueue_styles()
    {
        global $post;

        // Check if shortcode exists in post content
        if (isset($post) && is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'ricreviews')) {
            wp_enqueue_style(
                'ricreviews',
                RICREVIEWS_PLUGIN_URL . 'public/css/ricreviews.css',
                array(),
                RICREVIEWS_VERSION
            );
        }
    }

    /**
     * Maybe enqueue styles in footer (fallback for shortcode rendered after wp_enqueue_scripts)
     *
     * @return void
     */
    public function maybe_enqueue_styles_footer()
    {
        global $ricreviews_shortcode_used;

        // Check if shortcode was used during rendering
        if (isset($ricreviews_shortcode_used) && $ricreviews_shortcode_used) {
            // Enqueue styles if not already enqueued
            if (!wp_style_is('ricreviews', 'enqueued')) {
                wp_enqueue_style(
                    'ricreviews',
                    RICREVIEWS_PLUGIN_URL . 'public/css/ricreviews.css',
                    array(),
                    RICREVIEWS_VERSION
                );
            }
        }
    }
}
