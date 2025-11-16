<?php
/**
 * Plugin Name: RicReviews
 * Plugin URI: https://github.com/Richi2293/ricreviews
 * Description: Display Google Places reviews on your WordPress site using a simple shortcode.
 * Version: 1.0.0
 * Author: Riccardo Lorenzi
 * Author URI: https://github.com/Richi2293
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ricreviews
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RICREVIEWS_VERSION', '1.0.0');
define('RICREVIEWS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RICREVIEWS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RICREVIEWS_PLUGIN_FILE', __FILE__);
define('RICREVIEWS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader for classes
spl_autoload_register(function ($class) {
    // Check if class belongs to this plugin
    if (strpos($class, 'RicReviews_') !== 0) {
        return;
    }

    // Convert class name to file name
    $class_name = str_replace('RicReviews_', '', $class);
    $class_name = str_replace('_', '-', $class_name);
    $class_name = strtolower($class_name);
    
    $file = RICREVIEWS_PLUGIN_DIR . 'includes/class-' . $class_name . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

// Include required files
require_once RICREVIEWS_PLUGIN_DIR . 'includes/class-ricreviews-database.php';
require_once RICREVIEWS_PLUGIN_DIR . 'includes/class-ricreviews-api.php';
require_once RICREVIEWS_PLUGIN_DIR . 'includes/class-ricreviews-cache.php';
require_once RICREVIEWS_PLUGIN_DIR . 'includes/class-ricreviews-admin.php';
require_once RICREVIEWS_PLUGIN_DIR . 'includes/class-ricreviews-cron.php';
require_once RICREVIEWS_PLUGIN_DIR . 'includes/class-ricreviews-shortcode.php';

/**
 * Main plugin class
 */
class RicReviews {
    
    /**
     * Instance of this class
     *
     * @var RicReviews
     */
    private static $instance = null;
    
    /**
     * Get instance of this class
     *
     * @return RicReviews
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation hook
        register_activation_hook(RICREVIEWS_PLUGIN_FILE, array($this, 'activate'));
        
        // Deactivation hook
        register_deactivation_hook(RICREVIEWS_PLUGIN_FILE, array($this, 'deactivate'));
        
        // Initialize components
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database table
        $database = new RicReviews_Database();
        $database->create_table();
        
        // Schedule cron event
        $cron = new RicReviews_Cron();
        $cron->schedule_event();
        
        // Perform initial fetch if API key and Place ID are configured
        $api_key = get_option('ricreviews_api_key');
        $place_id = get_option('ricreviews_place_id');
        
        if ($api_key && $place_id) {
            $this->perform_initial_fetch($api_key, $place_id);
        }
        
        // Flush rewrite rules if needed
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled cron event
        $cron = new RicReviews_Cron();
        $cron->unschedule_event();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Initialize plugin components
     */
    public function init() {
        // Load plugin text domain for translations
        load_plugin_textdomain(
            'ricreviews',
            false,
            dirname(RICREVIEWS_PLUGIN_BASENAME) . '/languages'
        );
        
        // Initialize admin
        if (is_admin()) {
            new RicReviews_Admin();
        }
        
        // Initialize shortcode
        new RicReviews_Shortcode();
        
        // Initialize cron
        new RicReviews_Cron();
    }
    
    /**
     * Perform initial fetch of reviews
     *
     * @param string $api_key Google Places API key
     * @param string $place_id Google Place ID
     */
    private function perform_initial_fetch($api_key, $place_id) {
        $api = new RicReviews_API();
        $reviews = $api->fetch_reviews($place_id, $api_key);
        
        if (!is_wp_error($reviews) && !empty($reviews)) {
            // Save to database
            $database = new RicReviews_Database();
            $database->save_reviews($place_id, $reviews);
            
            // Update cache
            $cache = new RicReviews_Cache();
            $cache->set_cached_reviews($place_id, $reviews);
        }
    }
}

// Initialize plugin
RicReviews::get_instance();

