<?php
/**
 * Plugin Name: MKWA Fitness
 * Plugin URI: https://yoursite.com/mkwa-fitness
 * Description: A comprehensive fitness tracking and gamification system
 * Version: 1.0.0
 * Author: LastHopeForRaoha
 * Author URI: https://yoursite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mkwa-fitness
 * Domain Path: /languages
 *
 * @package MkwaFitness
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define error logging function first
if (!function_exists('mkwa_log')) {
    function mkwa_log($message) {
        if (WP_DEBUG === true) {
            if (is_array($message) || is_object($message)) {
                error_log(print_r($message, true));
            } else {
                error_log($message);
            }
        }
    }
}

// Define plugin constants
define('MKWA_VERSION', '1.0.0');
define('MKWA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MKWA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MKWA_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('MKWA_CURRENT_TIME', '2024-11-22 06:45:49'); // Updated to your current time

try {
    // Load required files first to ensure constants are available
    require_once MKWA_PLUGIN_DIR . 'includes/constants.php';
} catch (Exception $e) {
    mkwa_log('Error loading constants.php: ' . $e->getMessage());
}

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    try {
        $prefix = 'MKWA_';
        $base_dir = MKWA_PLUGIN_DIR . 'includes/';
        
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        
        $relative_class = substr($class, $len);
        $file = $base_dir . 'class-' . str_replace('_', '-', strtolower($relative_class)) . '.php';
        
        if (file_exists($file)) {
            require $file;
            mkwa_log("Successfully loaded class file: $file");
        } else {
            mkwa_log("Class file not found: $file");
        }
    } catch (Exception $e) {
        mkwa_log('Error in autoloader: ' . $e->getMessage());
    }
});

try {
    // Load functions after autoloader
    require_once MKWA_PLUGIN_DIR . 'includes/functions.php';
} catch (Exception $e) {
    mkwa_log('Error loading functions.php: ' . $e->getMessage());
}

/**
 * Main plugin class
 */
final class MKWA_Fitness {
    /**
     * Single instance of the plugin
     */
    private static $instance = null;

    /**
     * Get plugin instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        try {
            $this->init_hooks();
            mkwa_log('MKWA_Fitness instance constructed successfully');
        } catch (Exception $e) {
            mkwa_log('Error in MKWA_Fitness constructor: ' . $e->getMessage());
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'init_plugin'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        if (is_admin()) {
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        }
        mkwa_log('Hooks initialized successfully');
    }

    /**
     * Initialize plugin
     */
    public function init_plugin() {
        try {
            load_plugin_textdomain('mkwa-fitness', false, dirname(MKWA_PLUGIN_BASENAME) . '/languages');
            $this->maybe_init_database();
            mkwa_log('Plugin initialized successfully');
        } catch (Exception $e) {
            mkwa_log('Error initializing plugin: ' . $e->getMessage());
        }
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        try {
            if ('mkwa-fitness_page_mkwa-badges' === $hook) {
                wp_enqueue_media();
                wp_enqueue_style('mkwa-admin', MKWA_PLUGIN_URL . 'admin/css/admin.css', array(), MKWA_VERSION);
                wp_enqueue_script('mkwa-admin', MKWA_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), MKWA_VERSION, true);
                mkwa_log('Admin scripts enqueued successfully');
            }
        } catch (Exception $e) {
            mkwa_log('Error enqueuing admin scripts: ' . $e->getMessage());
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        try {
            global $wpdb;
            $charset_collate = $wpdb->get_charset_collate();
            mkwa_log('Starting plugin activation...');

            // Create members table
            $sql_members = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mkwa_members (
                member_id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                current_level int(11) NOT NULL DEFAULT 1,
                total_points int(11) NOT NULL DEFAULT 0,
                created_at datetime NOT NULL DEFAULT '" . MKWA_CURRENT_TIME . "',
                PRIMARY KEY  (member_id),
                KEY user_id (user_id)
            ) $charset_collate;";

            // Create badges table
            $sql_badges = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mkwa_badges (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                title varchar(255) NOT NULL,
                description text NOT NULL,
                icon_url varchar(255) NOT NULL,
                badge_type varchar(50) NOT NULL,
                category varchar(50) NOT NULL,
                points_required int(11) NOT NULL DEFAULT 0,
                activities_required text,
                cultural_requirement text,
                seasonal_requirement text,
                created_at datetime NOT NULL DEFAULT '" . MKWA_CURRENT_TIME . "',
                PRIMARY KEY  (id)
            ) $charset_collate;";

            // Create activity log table
            $sql_activity_log = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mkwa_activity_log (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                activity_type varchar(50) NOT NULL,
                points int(11) NOT NULL,
                logged_at datetime NOT NULL DEFAULT '" . MKWA_CURRENT_TIME . "',
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY activity_type (activity_type)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            mkwa_log('Creating database tables...');
            dbDelta($sql_members);
            dbDelta($sql_badges);
            dbDelta($sql_activity_log);

            // Set default options
            mkwa_log('Setting default options...');
            $default_options = array(
                'mkwa_points_checkin' => MKWA_POINTS_CHECKIN_DEFAULT,
                'mkwa_points_class' => MKWA_POINTS_CLASS_DEFAULT,
                'mkwa_points_cold_plunge' => MKWA_POINTS_COLD_PLUNGE_DEFAULT,
                'mkwa_points_pr' => MKWA_POINTS_PR_DEFAULT,
                'mkwa_points_competition' => MKWA_POINTS_COMPETITION_DEFAULT,
                'mkwa_cache_duration' => MKWA_CACHE_DURATION_DEFAULT,
            );

            foreach ($default_options as $key => $value) {
                add_option($key, $value);
            }

            // Ensure the current user is set up
            $user = wp_get_current_user();
            if ($user->exists()) {
                mkwa_ensure_member($user->ID);
                mkwa_log('Current user setup completed');
            }

            flush_rewrite_rules();
            mkwa_log('Plugin activated successfully');
            
        } catch (Exception $e) {
            mkwa_log('Error during plugin activation: ' . $e->getMessage());
            if (defined('WP_DEBUG') && WP_DEBUG) {
                mkwa_log($e->getTraceAsString());
            }
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        try {
            wp_clear_scheduled_hook('mkwa_daily_streak_check');
            flush_rewrite_rules();
            mkwa_log('Plugin deactivated successfully');
        } catch (Exception $e) {
            mkwa_log('Error during plugin deactivation: ' . $e->getMessage());
        }
    }

    /**
     * Initialize database if needed
     */
    private function maybe_init_database() {
        try {
            $db_version = get_option('mkwa_db_version');
            if ($db_version !== MKWA_VERSION) {
                mkwa_log('Database version mismatch. Current: ' . ($db_version ?: 'none') . ', Required: ' . MKWA_VERSION);
                $this->activate();
                update_option('mkwa_db_version', MKWA_VERSION);
                mkwa_log('Database initialized successfully');
            }
        } catch (Exception $e) {
            mkwa_log('Error initializing database: ' . $e->getMessage());
        }
    }
}

/**
 * Main plugin instance
 */
function mkwa_fitness() {
    try {
        return MKWA_Fitness::instance();
    } catch (Exception $e) {
        mkwa_log('Error getting MKWA_Fitness instance: ' . $e->getMessage());
        return null;
    }
}

// Initialize the plugin
try {
    mkwa_fitness();
    mkwa_log('Plugin initialization completed');
} catch (Exception $e) {
    mkwa_log('Error during plugin initialization: ' . $e->getMessage());
}