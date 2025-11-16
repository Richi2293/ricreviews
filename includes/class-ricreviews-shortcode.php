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
class RicReviews_Shortcode {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('ricreviews', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_styles'));
    }
    
    /**
     * Render shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_shortcode($atts) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'limit' => get_option('ricreviews_limit', 10),
            'order_by' => get_option('ricreviews_order_by', 'time'),
            'order' => get_option('ricreviews_order', 'DESC'),
        ), $atts, 'ricreviews');
        
        $place_id = get_option('ricreviews_place_id');
        
        if (empty($place_id)) {
            return '<p>' . esc_html__('Please configure RicReviews settings in WordPress admin.', 'ricreviews') . '</p>';
        }
        
        // Get reviews
        $reviews = $this->get_reviews($place_id, absint($atts['limit']), $atts['order_by'], $atts['order']);
        
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
     * @return array
     */
    private function get_reviews($place_id, $limit, $order_by, $order) {
        // Try cache first
        $cache = new RicReviews_Cache();
        $cached_reviews = $cache->get_cached_reviews($place_id);
        
        if (!empty($cached_reviews)) {
            return $this->apply_sorting_and_limit($cached_reviews, $limit, $order_by, $order);
        }
        
        // Try database
        $database = new RicReviews_Database();
        $db_reviews = $database->get_reviews($place_id, $limit, $order_by, $order);
        
        if (!empty($db_reviews)) {
            return $db_reviews;
        }
        
        // Fallback to API fetch
        $api_key = get_option('ricreviews_api_key');
        if (empty($api_key)) {
            return array();
        }
        
        $api = new RicReviews_API();
        $reviews = $api->fetch_reviews($place_id, $api_key);
        
        if (is_wp_error($reviews) || empty($reviews)) {
            return array();
        }
        
        // Save to database and cache
        $database->save_reviews($place_id, $reviews);
        $cache->set_cached_reviews($place_id, $reviews);
        
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
    private function apply_sorting_and_limit($reviews, $limit, $order_by, $order) {
        if (empty($reviews)) {
            return array();
        }
        
        // Handle time_asc special case
        if ($order_by === 'time_asc') {
            $order_by = 'time';
            $order = 'ASC';
        }
        
        // Sort reviews
        usort($reviews, function($a, $b) use ($order_by, $order) {
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
    private function render_reviews($reviews) {
        $primary_color = get_option('ricreviews_primary_color', '#0073aa');
        $theme = get_option('ricreviews_theme', 'light');
        
        ob_start();
        ?>
        <div class="ricreviews-container ricreviews-theme-<?php echo esc_attr($theme); ?>" 
             style="--ricreviews-primary-color: <?php echo esc_attr($primary_color); ?>">
            <div class="ricreviews-list">
                <?php foreach ($reviews as $review) : ?>
                    <div class="ricreviews-item">
                        <div class="ricreviews-item__header">
                            <?php if (!empty($review['profile_photo_url'])) : ?>
                                <div class="ricreviews-item__avatar">
                                    <img src="<?php echo esc_url($review['profile_photo_url']); ?>" 
                                         alt="<?php echo esc_attr($review['author_name']); ?>" />
                                </div>
                            <?php endif; ?>
                            
                            <div class="ricreviews-item__author">
                                <div class="ricreviews-item__name">
                                    <?php if (!empty($review['author_url'])) : ?>
                                        <a href="<?php echo esc_url($review['author_url']); ?>" 
                                           target="_blank" 
                                           rel="noopener noreferrer">
                                            <?php echo esc_html($review['author_name']); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo esc_html($review['author_name']); ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="ricreviews-item__rating">
                                    <?php echo $this->render_stars($review['rating']); ?>
                                </div>
                                
                                <?php if (!empty($review['relative_time_description'])) : ?>
                                    <div class="ricreviews-item__time">
                                        <?php echo esc_html($review['relative_time_description']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($review['text'])) : ?>
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
    private function render_stars($rating) {
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
     * Set flag that shortcode is used (for CSS enqueue)
     *
     * @return void
     */
    private function set_shortcode_used() {
        global $ricreviews_shortcode_used;
        $ricreviews_shortcode_used = true;
    }
    
    /**
     * Maybe enqueue styles if shortcode is used
     *
     * @return void
     */
    public function maybe_enqueue_styles() {
        global $ricreviews_shortcode_used;
        
        // Check if shortcode is used on current page
        if (isset($ricreviews_shortcode_used) && $ricreviews_shortcode_used) {
            wp_enqueue_style(
                'ricreviews',
                RICREVIEWS_PLUGIN_URL . 'public/css/ricreviews.css',
                array(),
                RICREVIEWS_VERSION
            );
        }
    }
}

