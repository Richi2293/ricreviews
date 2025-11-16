<?php
/**
 * Admin settings page for RicReviews
 *
 * @package RicReviews
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RicReviews_Admin
 */
class RicReviews_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_notices', array($this, 'display_admin_notices'));
        add_action('wp_ajax_ricreviews_validate_api_key', array($this, 'ajax_validate_api_key'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('RicReviews Settings', 'ricreviews'),
            __('RicReviews', 'ricreviews'),
            'manage_options',
            'ricreviews',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // Register settings
        register_setting('ricreviews_settings', 'ricreviews_api_key', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ));
        
        register_setting('ricreviews_settings', 'ricreviews_place_id', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ));
        
        register_setting('ricreviews_settings', 'ricreviews_limit', array(
            'sanitize_callback' => 'absint',
            'default' => 10,
        ));
        
        register_setting('ricreviews_settings', 'ricreviews_order_by', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'time',
        ));
        
        register_setting('ricreviews_settings', 'ricreviews_order', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'DESC',
        ));
        
        register_setting('ricreviews_settings', 'ricreviews_primary_color', array(
            'sanitize_callback' => 'sanitize_hex_color',
            'default' => '#0073aa',
        ));
        
        register_setting('ricreviews_settings', 'ricreviews_theme', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'light',
        ));
    }
    
    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_ricreviews') {
            return;
        }
        
        wp_enqueue_style(
            'ricreviews-admin',
            RICREVIEWS_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            RICREVIEWS_VERSION
        );
        
        wp_enqueue_script(
            'ricreviews-admin',
            RICREVIEWS_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            RICREVIEWS_VERSION,
            true
        );
        
        wp_localize_script('ricreviews-admin', 'ricreviewsAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ricreviews_admin_nonce'),
        ));
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'ricreviews'));
        }
        
        // Handle form submission
        if (isset($_POST['ricreviews_save_settings']) && check_admin_referer('ricreviews_settings_nonce', 'ricreviews_nonce')) {
            $this->save_settings();
        }
        
        // Get current settings
        $api_key = get_option('ricreviews_api_key', '');
        $place_id = get_option('ricreviews_place_id', '');
        $limit = get_option('ricreviews_limit', 10);
        $order_by = get_option('ricreviews_order_by', 'time');
        $order = get_option('ricreviews_order', 'DESC');
        $primary_color = get_option('ricreviews_primary_color', '#0073aa');
        $theme = get_option('ricreviews_theme', 'light');
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="" class="ricreviews-settings-form">
                <?php wp_nonce_field('ricreviews_settings_nonce', 'ricreviews_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ricreviews_api_key"><?php esc_html_e('Google Places API Key', 'ricreviews'); ?></label>
                        </th>
                        <td>
                            <input 
                                type="text" 
                                id="ricreviews_api_key" 
                                name="ricreviews_api_key" 
                                value="<?php echo esc_attr($api_key); ?>" 
                                class="regular-text"
                                required
                            />
                            <p class="description">
                                <?php esc_html_e('Enter your Google Places API key. Get one from', 'ricreviews'); ?>
                                <a href="https://console.cloud.google.com/google/maps-apis" target="_blank">
                                    <?php esc_html_e('Google Cloud Console', 'ricreviews'); ?>
                                </a>
                            </p>
                            <div id="ricreviews-api-key-validation" class="ricreviews-validation-message"></div>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ricreviews_place_id"><?php esc_html_e('Place ID', 'ricreviews'); ?></label>
                        </th>
                        <td>
                            <input 
                                type="text" 
                                id="ricreviews_place_id" 
                                name="ricreviews_place_id" 
                                value="<?php echo esc_attr($place_id); ?>" 
                                class="regular-text"
                                required
                            />
                            <p class="description">
                                <?php esc_html_e('Enter the Google Place ID for the location you want to display reviews for.', 'ricreviews'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ricreviews_limit"><?php esc_html_e('Number of Reviews', 'ricreviews'); ?></label>
                        </th>
                        <td>
                            <select id="ricreviews_limit" name="ricreviews_limit">
                                <option value="5" <?php selected($limit, 5); ?>>5</option>
                                <option value="10" <?php selected($limit, 10); ?>>10</option>
                                <option value="15" <?php selected($limit, 15); ?>>15</option>
                                <option value="20" <?php selected($limit, 20); ?>>20</option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Select how many reviews to display.', 'ricreviews'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ricreviews_order_by"><?php esc_html_e('Sort By', 'ricreviews'); ?></label>
                        </th>
                        <td>
                            <select id="ricreviews_order_by" name="ricreviews_order_by">
                                <option value="time" <?php selected($order_by, 'time'); ?>>
                                    <?php esc_html_e('Most Recent', 'ricreviews'); ?>
                                </option>
                                <option value="time_asc" <?php selected($order_by, 'time_asc'); ?>>
                                    <?php esc_html_e('Oldest First', 'ricreviews'); ?>
                                </option>
                                <option value="rating" <?php selected($order_by, 'rating'); ?>>
                                    <?php esc_html_e('Highest Rating', 'ricreviews'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Select how to sort the reviews.', 'ricreviews'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ricreviews_primary_color"><?php esc_html_e('Primary Color', 'ricreviews'); ?></label>
                        </th>
                        <td>
                            <input 
                                type="color" 
                                id="ricreviews_primary_color" 
                                name="ricreviews_primary_color" 
                                value="<?php echo esc_attr($primary_color); ?>"
                            />
                            <p class="description">
                                <?php esc_html_e('Choose the primary color for the reviews display.', 'ricreviews'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ricreviews_theme"><?php esc_html_e('Theme', 'ricreviews'); ?></label>
                        </th>
                        <td>
                            <select id="ricreviews_theme" name="ricreviews_theme">
                                <option value="light" <?php selected($theme, 'light'); ?>>
                                    <?php esc_html_e('Light', 'ricreviews'); ?>
                                </option>
                                <option value="dark" <?php selected($theme, 'dark'); ?>>
                                    <?php esc_html_e('Dark', 'ricreviews'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Select the theme for the reviews display.', 'ricreviews'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Settings', 'ricreviews'), 'primary', 'ricreviews_save_settings'); ?>
            </form>
            
            <div class="ricreviews-shortcode-info">
                <h2><?php esc_html_e('Usage', 'ricreviews'); ?></h2>
                <p>
                    <?php esc_html_e('Use the following shortcode to display reviews on any page or post:', 'ricreviews'); ?>
                </p>
                <code>[ricreviews]</code>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        // Validate and save API key
        $api_key = isset($_POST['ricreviews_api_key']) ? sanitize_text_field($_POST['ricreviews_api_key']) : '';
        $place_id = isset($_POST['ricreviews_place_id']) ? sanitize_text_field($_POST['ricreviews_place_id']) : '';
        
        // Validate API key if provided
        if (!empty($api_key) && !empty($place_id)) {
            $api = new RicReviews_API();
            $validation = $api->validate_api_key($api_key, $place_id);
            
            if (is_wp_error($validation)) {
                add_settings_error(
                    'ricreviews_api_key',
                    'invalid_api_key',
                    $validation->get_error_message(),
                    'error'
                );
                return;
            }
        }
        
        // Save all settings
        $order_by = isset($_POST['ricreviews_order_by']) ? sanitize_text_field($_POST['ricreviews_order_by']) : 'time';
        
        update_option('ricreviews_api_key', $api_key);
        update_option('ricreviews_place_id', $place_id);
        update_option('ricreviews_limit', isset($_POST['ricreviews_limit']) ? absint($_POST['ricreviews_limit']) : 10);
        update_option('ricreviews_order_by', $order_by);
        update_option('ricreviews_order', $order_by === 'time_asc' ? 'ASC' : 'DESC');
        update_option('ricreviews_primary_color', isset($_POST['ricreviews_primary_color']) ? sanitize_hex_color($_POST['ricreviews_primary_color']) : '#0073aa');
        update_option('ricreviews_theme', isset($_POST['ricreviews_theme']) ? sanitize_text_field($_POST['ricreviews_theme']) : 'light');
        
        // Clear cache when settings are updated
        $cache = new RicReviews_Cache();
        $cache->clear_cache($place_id);
        
        // Fetch and save reviews if API key and Place ID are set
        if (!empty($api_key) && !empty($place_id)) {
            $api = new RicReviews_API();
            $reviews = $api->fetch_reviews($place_id, $api_key);
            
            if (!is_wp_error($reviews) && !empty($reviews)) {
                $database = new RicReviews_Database();
                $database->save_reviews($place_id, $reviews);
                
                $cache->set_cached_reviews($place_id, $reviews);
            }
        }
        
        add_settings_error(
            'ricreviews_settings',
            'settings_saved',
            __('Settings saved successfully.', 'ricreviews'),
            'updated'
        );
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        settings_errors('ricreviews_settings');
        settings_errors('ricreviews_api_key');
    }
    
    /**
     * AJAX handler for API key validation
     *
     * @return void
     */
    public function ajax_validate_api_key() {
        check_ajax_referer('ricreviews_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'ricreviews')));
        }
        
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        $place_id = isset($_POST['place_id']) ? sanitize_text_field($_POST['place_id']) : '';
        
        if (empty($api_key) || empty($place_id)) {
            wp_send_json_error(array('message' => __('API key and Place ID are required.', 'ricreviews')));
        }
        
        $api = new RicReviews_API();
        $validation = $api->validate_api_key($api_key, $place_id);
        
        if (is_wp_error($validation)) {
            wp_send_json_error(array('message' => $validation->get_error_message()));
        }
        
        wp_send_json_success(array('message' => __('API key is valid.', 'ricreviews')));
    }
}

