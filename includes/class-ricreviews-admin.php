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
class RicReviews_Admin
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_notices', array($this, 'display_admin_notices'));
        add_action('wp_ajax_ricreviews_validate_api_key', array($this, 'ajax_validate_api_key'));
        add_action('wp_ajax_ricreviews_fetch_reviews', array($this, 'ajax_fetch_reviews'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
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
    public function register_settings()
    {
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

        register_setting('ricreviews_settings', 'ricreviews_languages', array(
            'sanitize_callback' => array($this, 'sanitize_languages'),
            'default' => '',
        ));

        register_setting('ricreviews_settings', 'ricreviews_debug_logging', array(
            'sanitize_callback' => array($this, 'sanitize_toggle'),
            'default' => 'no',
        ));

        register_setting('ricreviews_settings', 'ricreviews_cron_enabled', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'yes',
        ));

        register_setting('ricreviews_settings', 'ricreviews_cron_frequency', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'daily',
        ));
    }

    /**
     * Sanitize languages input (comma-separated language codes)
     *
     * @param string $input Raw input
     * @return string Sanitized languages (comma-separated)
     */
    public function sanitize_languages($input)
    {
        if (empty($input)) {
            return '';
        }

        // Split by comma and validate each language code against BCP47 format
        $languages = explode(',', $input);
        $valid = array();

        foreach ($languages as $lang) {
            $lang = trim($lang);
            // Validate BCP47 locale codes: e.g. 'en', 'fr', 'pt-BR', 'zh-CN'
            if (preg_match('/^[a-z]{2,3}(-[A-Z]{2})?$/', $lang)) {
                $valid[] = $lang;
            }
        }

        return implode(',', array_unique($valid));
    }

    /**
     * Sanitize checkbox-like inputs returning "yes" or "no".
     *
     * @param string $value Raw value.
     * @return string
     */
    public function sanitize_toggle($value)
    {
        return $value === 'yes' ? 'yes' : 'no';
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_scripts($hook)
    {
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

        wp_add_inline_script(
            'ricreviews-admin',
            'jQuery(document).ready(function ($) {
                function toggleFrequency() {
                    if ($("#ricreviews_cron_enabled").is(":checked")) {
                        $("#ricreviews_cron_frequency_row").show();
                    } else {
                        $("#ricreviews_cron_frequency_row").hide();
                    }
                }
                $("#ricreviews_cron_enabled").on("change", toggleFrequency);
                toggleFrequency();
            });'
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ricreviews'));
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
        $languages = get_option('ricreviews_languages', '');
        $cron_enabled = get_option('ricreviews_cron_enabled', 'yes');
        $cron_frequency = get_option('ricreviews_cron_frequency', 'daily');
        $debug_logging = get_option('ricreviews_debug_logging', 'no');

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="notice notice-info is-dismissible" style="margin-top: 20px;">
                <p>
                    <strong><?php esc_html_e('ℹ️ Important Information:', 'ricreviews'); ?></strong>
                </p>
                <p>
                    <?php esc_html_e('Google Places API returns a maximum of 5 reviews per place. This is a limitation imposed by Google, not by the plugin.', 'ricreviews'); ?>
                </p>
                <p>
                    <?php esc_html_e('The plugin performs periodic fetches (configurable below) to update reviews. Over time, you may accumulate more reviews in the database as Google updates the "5 most helpful reviews" for your place.', 'ricreviews'); ?>
                </p>
                <p>
                    <strong><?php esc_html_e('To get all reviews:', 'ricreviews'); ?></strong>
                    <?php esc_html_e('If you are the owner of the business, you can use Google My Business API to access all reviews. Otherwise, this limitation is unavoidable with Google\'s official APIs.', 'ricreviews'); ?>
                </p>
                <p>
                    <a href="https://developers.google.com/maps/documentation/places/web-service/policies?hl=it#review-policy"
                        target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Read Google\'s official documentation →', 'ricreviews'); ?>
                    </a>
                </p>
            </div>

            <form method="post" action="" class="ricreviews-settings-form">
                <?php wp_nonce_field('ricreviews_settings_nonce', 'ricreviews_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ricreviews_api_key"><?php esc_html_e('Google Places API Key', 'ricreviews'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="ricreviews_api_key" name="ricreviews_api_key"
                                value="<?php echo esc_attr($api_key); ?>" class="regular-text" required />
                            <p class="description">
                                <?php esc_html_e('Enter your Google Places API key. Get one from', 'ricreviews'); ?>
                                <a href="https://console.cloud.google.com/google/maps-apis" target="_blank"
                                    rel="noopener noreferrer">
                                    <?php esc_html_e('Google Cloud Console', 'ricreviews'); ?>
                                </a>
                                <br>
                                <strong style="color: #d63638; display: block; margin-top: 10px;">
                                    ⚠ <?php esc_html_e('Important:', 'ricreviews'); ?>
                                </strong>
                                <?php esc_html_e('You must enable', 'ricreviews'); ?>
                                <strong><?php esc_html_e('Places API (New)', 'ricreviews'); ?></strong>
                                <?php esc_html_e('in your Google Cloud Console. The legacy Places API is no longer available for new projects.', 'ricreviews'); ?>
                                <br>
                                <a href="https://console.cloud.google.com/google/maps-apis/credentials" target="_blank"
                                    rel="noopener noreferrer">
                                    <?php esc_html_e('Enable Places API (New) →', 'ricreviews'); ?>
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
                            <input type="text" id="ricreviews_place_id" name="ricreviews_place_id"
                                value="<?php echo esc_attr($place_id); ?>" class="regular-text" required
                                placeholder="ChIJN1t_tDeuEmsRUsoyG83frY4" />
                            <p class="description">
                                <?php esc_html_e('Enter the Google Place ID for the location you want to display reviews for.', 'ricreviews'); ?>
                                <br>
                                <strong><?php esc_html_e('How to find your Place ID:', 'ricreviews'); ?></strong>
                            <ol style="margin: 10px 0; padding-left: 20px;">
                                <li><?php esc_html_e('Go to', 'ricreviews'); ?> <a
                                        href="https://developers.google.com/maps/documentation/places/web-service/place-id"
                                        target="_blank"
                                        rel="noopener noreferrer"><?php esc_html_e('Google Maps', 'ricreviews'); ?></a>
                                    <?php esc_html_e('and search for your business location', 'ricreviews'); ?></li>
                                <li><?php esc_html_e('Click on your business in the search results or on the map', 'ricreviews'); ?>
                                </li>
                                <li><?php esc_html_e('In the business information panel, scroll down and look for the Place ID (it looks like: ChIJN1t_tDeuEmsRUsoyG83frY4)', 'ricreviews'); ?>
                                </li>
                                <li><?php esc_html_e('Alternatively, use the', 'ricreviews'); ?> <a
                                        href="https://developers.google.com/maps/documentation/places/web-service/place-id#find-id"
                                        target="_blank"
                                        rel="noopener noreferrer"><?php esc_html_e('Place ID Finder tool', 'ricreviews'); ?></a>
                                    <?php esc_html_e('from Google', 'ricreviews'); ?></li>
                            </ol>
                            <a href="https://developers.google.com/maps/documentation/places/web-service/place-id"
                                target="_blank" rel="noopener noreferrer" style="text-decoration: none;">
                                <?php esc_html_e('Learn more about Place IDs →', 'ricreviews'); ?>
                            </a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label
                                for="ricreviews_languages"><?php esc_html_e('Additional Languages (Optional)', 'ricreviews'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="ricreviews_languages" name="ricreviews_languages"
                                value="<?php echo esc_attr($languages); ?>" class="regular-text" placeholder="en,fr,de" />
                            <p class="description">
                                <?php esc_html_e('Enter additional language codes separated by commas (e.g., "en,fr,de"). The plugin will fetch reviews in these languages in addition to the default language.', 'ricreviews'); ?>
                                <br>
                                <strong><?php esc_html_e('Note:', 'ricreviews'); ?></strong>
                                <?php esc_html_e('Each language requires a separate API call. Leave empty to use only the default language (based on WordPress locale).', 'ricreviews'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label
                                for="ricreviews_debug_logging"><?php esc_html_e('Enable Debug Logging', 'ricreviews'); ?></label>
                        </th>
                        <td>
                            <label class="switch">
                                <input type="checkbox" id="ricreviews_debug_logging" name="ricreviews_debug_logging" value="yes"
                                    <?php checked($debug_logging, 'yes'); ?> />
                                <span class="slider round"></span>
                            </label>
                            <p class="description">
                                <?php esc_html_e('When enabled, the plugin writes API request and response details to the WordPress debug log. Make sure WP_DEBUG and WP_DEBUG_LOG are enabled.', 'ricreviews'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label
                                for="ricreviews_cron_enabled"><?php esc_html_e('Enable Automatic Updates', 'ricreviews'); ?></label>
                        </th>
                        <td>
                            <label class="switch">
                                <input type="checkbox" id="ricreviews_cron_enabled" name="ricreviews_cron_enabled" value="yes"
                                    <?php checked($cron_enabled, 'yes'); ?> />
                                <span class="slider round"></span>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Enable or disable the automatic fetching of reviews.', 'ricreviews'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr id="ricreviews_cron_frequency_row">
                        <th scope="row">
                            <label
                                for="ricreviews_cron_frequency"><?php esc_html_e('Update Frequency', 'ricreviews'); ?></label>
                        </th>
                        <td>
                            <select id="ricreviews_cron_frequency" name="ricreviews_cron_frequency">
                                <option value="daily" <?php selected($cron_frequency, 'daily'); ?>>
                                    <?php esc_html_e('Daily', 'ricreviews'); ?>
                                </option>
                                <option value="weekly" <?php selected($cron_frequency, 'weekly'); ?>>
                                    <?php esc_html_e('Weekly', 'ricreviews'); ?>
                                </option>
                                <option value="monthly" <?php selected($cron_frequency, 'monthly'); ?>>
                                    <?php esc_html_e('Monthly', 'ricreviews'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Select how often to fetch new reviews.', 'ricreviews'); ?>
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
                            <input type="color" id="ricreviews_primary_color" name="ricreviews_primary_color"
                                value="<?php echo esc_attr($primary_color); ?>" />
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
                <p>
                    <strong><?php esc_html_e('Shortcode Parameters:', 'ricreviews'); ?></strong>
                </p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><code>limit</code> -
                        <?php esc_html_e('Number of reviews to display (default: from settings)', 'ricreviews'); ?>
                    </li>
                    <li><code>order_by</code> -
                        <?php esc_html_e('Sort by: time, time_asc, rating (default: from settings)', 'ricreviews'); ?>
                    </li>
                    <li><code>order</code> -
                        <?php esc_html_e('Order direction: ASC or DESC (default: from settings)', 'ricreviews'); ?>
                    </li>
                    <li><code>language</code> -
                        <?php esc_html_e('Language code to filter reviews (e.g., "it", "en"). If not specified, uses WordPress locale (default)', 'ricreviews'); ?>
                    </li>
                </ul>
                <p>
                    <strong><?php esc_html_e('Examples:', 'ricreviews'); ?></strong>
                </p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><code>[ricreviews]</code> -
                        <?php esc_html_e('Display reviews with default settings and WordPress locale', 'ricreviews'); ?>
                    </li>
                    <li><code>[ricreviews language="en"]</code> -
                        <?php esc_html_e('Display only English reviews', 'ricreviews'); ?>
                    </li>
                    <li><code>[ricreviews limit="5" language="it"]</code> -
                        <?php esc_html_e('Display 5 Italian reviews', 'ricreviews'); ?>
                    </li>
                </ul>
            </div>

            <?php $this->render_reviews_preview(); ?>
        </div>
        <?php
    }

    /**
     * Render reviews preview in admin
     *
     * @return void
     */
    private function render_reviews_preview()
    {
        $place_id = get_option('ricreviews_place_id', '');

        if (empty($place_id)) {
            return;
        }

        $database = new RicReviews_Database();
        $limit = get_option('ricreviews_limit', 10);
        $order_by = get_option('ricreviews_order_by', 'time');
        $order = get_option('ricreviews_order', 'DESC');

        // Get reviews from database
        $reviews = $database->get_reviews($place_id, $limit, $order_by, $order);

        // Get last fetch timestamp
        $last_fetch = get_option('ricreviews_last_fetch', '');

        ?>
        <div class="ricreviews-reviews-preview">
            <h2><?php esc_html_e('Reviews Preview', 'ricreviews'); ?></h2>

            <?php if (!empty($last_fetch)): ?>
                <div class="ricreviews-last-fetch-info"
                    style="margin-bottom: 15px; padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1; border-radius: 4px;">
                    <p style="margin: 0;">
                        <strong><?php esc_html_e('Last fetch:', 'ricreviews'); ?></strong>
                        <?php
                        $last_fetch_timestamp = strtotime($last_fetch);
                        $formatted_date = date_i18n(
                            get_option('date_format') . ' ' . get_option('time_format'),
                            $last_fetch_timestamp
                        );
                        echo esc_html($formatted_date);

                        // Show relative time
                        $time_diff = human_time_diff($last_fetch_timestamp, current_time('timestamp'));
                        echo ' <span style="color: #646970;">(' . esc_html($time_diff . ' ' . __('ago', 'ricreviews')) . ')</span>';
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (empty($reviews)): ?>
                <div class="ricreviews-no-reviews">
                    <p>
                        <strong><?php esc_html_e('No reviews found.', 'ricreviews'); ?></strong>
                    </p>
                    <p>
                        <?php esc_html_e('Make sure you have:', 'ricreviews'); ?>
                    </p>
                    <ul>
                        <li><?php esc_html_e('Entered a valid Place ID', 'ricreviews'); ?></li>
                        <li><?php esc_html_e('Entered a valid API key with Places API (New) enabled', 'ricreviews'); ?></li>
                        <li><?php esc_html_e('Saved the settings to fetch reviews', 'ricreviews'); ?></li>
                    </ul>
                    <p>
                        <button type="button" class="button" id="ricreviews-fetch-now">
                            <?php esc_html_e('Fetch Reviews Now', 'ricreviews'); ?>
                        </button>
                    </p>
                    <div id="ricreviews-fetch-error" class="ricreviews-fetch-error" style="display: none;"></div>
                    <div id="ricreviews-debug-info" class="ricreviews-debug-info" style="display: none;">
                        <h4><?php esc_html_e('Debug Information', 'ricreviews'); ?></h4>
                        <pre id="ricreviews-debug-content"></pre>
                    </div>
                </div>
            <?php else: ?>
                <div class="ricreviews-reviews-stats">
                    <p>
                        <strong><?php echo esc_html(count($reviews)); ?></strong>
                        <?php esc_html_e('review(s) found in database.', 'ricreviews'); ?>
                    </p>
                    <?php if (count($reviews) <= 5): ?>
                        <div class="notice notice-info inline" style="margin: 10px 0;">
                            <p>
                                <strong><?php esc_html_e('Note:', 'ricreviews'); ?></strong>
                                <?php esc_html_e('Google Places API returns a maximum of 5 reviews per fetch. The plugin automatically updates reviews every 24 hours via cron job.', 'ricreviews'); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="ricreviews-reviews-list">
                    <?php foreach ($reviews as $review): ?>
                        <div class="ricreviews-review-item">
                            <div class="ricreviews-review-header">
                                <?php if (!empty($review['profile_photo_url'])): ?>
                                    <div class="ricreviews-review-avatar">
                                        <img
                                            src="<?php echo esc_url($review['profile_photo_url']); ?>"
                                            alt="<?php echo esc_attr($review['author_name']); ?>"
                                            width="40"
                                            height="40"
                                            loading="lazy"
                                            decoding="async"
                                            referrerpolicy="no-referrer"
                                        />
                                    </div>
                                <?php endif; ?>

                                <div class="ricreviews-review-author">
                                    <strong><?php echo esc_html($review['author_name']); ?></strong>
                                    <div class="ricreviews-review-rating">
                                        <?php echo wp_kses_post($this->render_stars($review['rating'])); ?>
                                        <span class="ricreviews-review-rating-value"><?php echo esc_html($review['rating']); ?>/5</span>
                                    </div>
                                    <?php if (!empty($review['relative_time_description'])): ?>
                                        <span
                                            class="ricreviews-review-time"><?php echo esc_html($review['relative_time_description']); ?></span>
                                    <?php elseif (!empty($review['time'])): ?>
                                        <span class="ricreviews-review-time">
                                            <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $review['time'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($review['text'])): ?>
                                <div class="ricreviews-review-text">
                                    <?php echo wp_kses_post($review['text']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
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

        $output = '<span class="ricreviews-stars">';
        for ($i = 1; $i <= 5; $i++) {
            $class = $i <= $rating ? 'ricreviews-star--filled' : 'ricreviews-star--empty';
            $output .= '<span class="ricreviews-star ' . esc_attr($class) . '">★</span>';
        }
        $output .= '</span>';

        return $output;
    }

    /**
     * Save settings
     */
    private function save_settings()
    {
        // Nonce verification — already verified in render_settings_page(), but we verify again
        // here to satisfy static analysis tools that inspect this method in isolation.
        check_admin_referer('ricreviews_settings_nonce', 'ricreviews_nonce');

        // Validate and save API key
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- wp_unslash applied below
        $api_key = isset($_POST['ricreviews_api_key']) ? sanitize_text_field(wp_unslash($_POST['ricreviews_api_key'])) : '';
        $place_id = isset($_POST['ricreviews_place_id']) ? sanitize_text_field(wp_unslash($_POST['ricreviews_place_id'])) : '';

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
        $order_by = isset($_POST['ricreviews_order_by']) ? sanitize_text_field(wp_unslash($_POST['ricreviews_order_by'])) : 'time';

        update_option('ricreviews_api_key', $api_key);
        update_option('ricreviews_place_id', $place_id);
        update_option('ricreviews_limit', isset($_POST['ricreviews_limit']) ? absint($_POST['ricreviews_limit']) : 10);
        update_option('ricreviews_order_by', $order_by);
        update_option('ricreviews_order', $order_by === 'time_asc' ? 'ASC' : 'DESC');
        update_option('ricreviews_primary_color', isset($_POST['ricreviews_primary_color']) ? sanitize_hex_color(wp_unslash($_POST['ricreviews_primary_color'])) : '#0073aa');
        update_option('ricreviews_theme', isset($_POST['ricreviews_theme']) ? sanitize_text_field(wp_unslash($_POST['ricreviews_theme'])) : 'light');
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_languages() is a custom sanitization callback that validates each language code against an allowlist.
        update_option('ricreviews_languages', isset($_POST['ricreviews_languages']) ? $this->sanitize_languages(wp_unslash($_POST['ricreviews_languages'])) : '');
        update_option('ricreviews_debug_logging', isset($_POST['ricreviews_debug_logging']) ? 'yes' : 'no');

        // Save cron settings
        $cron_enabled = isset($_POST['ricreviews_cron_enabled']) ? 'yes' : 'no';
        $cron_frequency = isset($_POST['ricreviews_cron_frequency']) ? sanitize_text_field(wp_unslash($_POST['ricreviews_cron_frequency'])) : 'daily';

        update_option('ricreviews_cron_enabled', $cron_enabled);
        update_option('ricreviews_cron_frequency', $cron_frequency);

        // Reschedule cron event
        $cron = new RicReviews_Cron();
        $cron->reschedule_event();

        // Clear cache when settings are updated
        $cache = new RicReviews_Cache();
        $cache->clear_cache($place_id);

        // Fetch and save reviews if API key and Place ID are set
        if (!empty($api_key) && !empty($place_id)) {
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

            if (!is_wp_error($reviews) && !empty($reviews)) {
                $database = new RicReviews_Database();
                $database->save_reviews($place_id, $reviews);

                $cache->set_cached_reviews($place_id, $reviews);

                // Update last fetch timestamp
                update_option('ricreviews_last_fetch', current_time('mysql'));
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
    public function display_admin_notices()
    {
        settings_errors('ricreviews_settings');
        settings_errors('ricreviews_api_key');
    }

    /**
     * AJAX handler for API key validation
     *
     * @return void
     */
    public function ajax_validate_api_key()
    {
        check_ajax_referer('ricreviews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'ricreviews')));
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
        $place_id = isset($_POST['place_id']) ? sanitize_text_field(wp_unslash($_POST['place_id'])) : '';

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

    /**
     * AJAX handler for manual review fetch
     *
     * @return void
     */
    public function ajax_fetch_reviews()
    {
        check_ajax_referer('ricreviews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'ricreviews')));
        }

        $api_key = get_option('ricreviews_api_key');
        $place_id = get_option('ricreviews_place_id');

        if (empty($api_key) || empty($place_id)) {
            wp_send_json_error(array('message' => __('API key and Place ID are required.', 'ricreviews')));
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
            $error_message = $reviews->get_error_message();
            $error_code = $reviews->get_error_code();

            // Add more context for debugging
            $debug_info = '';
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $debug_info = ' | Error Code: ' . $error_code;
                if ($error_code === 'invalid_place') {
                    $debug_info .= ' | Place ID: ' . $place_id;
                }
            }

            wp_send_json_error(array(
                'message' => $error_message . $debug_info,
                'code' => $error_code
            ));
        }

        if (empty($reviews)) {
            wp_send_json_error(array('message' => __('No reviews found for this Place ID.', 'ricreviews')));
        }

        // Save to database
        $database = new RicReviews_Database();
        $database->save_reviews($place_id, $reviews);

        // Update cache
        $cache = new RicReviews_Cache();
        $cache->set_cached_reviews($place_id, $reviews);

        // Update last fetch timestamp
        update_option('ricreviews_last_fetch', current_time('mysql'));

        wp_send_json_success(array(
            // translators: %d is the number of reviews successfully fetched.
            'message' => sprintf(__('Successfully fetched %d review(s).', 'ricreviews'), count($reviews)),
            'count' => count($reviews)
        ));
    }
}
