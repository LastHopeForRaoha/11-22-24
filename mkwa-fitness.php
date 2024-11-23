<?php
/**
 * Plugin Name: MKWA Fitness
 * [Your existing plugin header remains the same...]
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
define('MKWA_CURRENT_TIME', '2024-11-23 22:05:45'); // Updated to current time

// Load required files
try {
    require_once MKWA_PLUGIN_DIR . 'includes/constants.php';
} catch (Exception $e) {
    mkwa_log('Error loading constants.php: ' . $e->getMessage());
}

// Your existing autoloader
spl_autoload_register(function ($class) {
    // [Your existing autoloader code remains the same...]
});

// Load required files
try {
    require_once MKWA_PLUGIN_DIR . 'includes/functions.php';
    require_once MKWA_PLUGIN_DIR . 'includes/points-functions.php';
    require_once MKWA_PLUGIN_DIR . 'includes/badge-system.php';
    require_once MKWA_PLUGIN_DIR . 'includes/class-mkwa-frontend.php';
    require_once MKWA_PLUGIN_DIR . 'includes/class-mkwa-ajax.php';
    require_once MKWA_PLUGIN_DIR . 'includes/class-mkwa-classes.php';
    require_once MKWA_PLUGIN_DIR . 'includes/class-mkwa-member-score.php'; // New file
} catch (Exception $e) {
    mkwa_log('Error loading required files: ' . $e->getMessage());
}

// Initialize member score system
function mkwa_init_member_score() {
    global $mkwa_member_score;
    if (!isset($mkwa_member_score)) {
        $mkwa_member_score = new MKWA_Member_Score();
        $mkwa_member_score->init();
    }
    return $mkwa_member_score;
}
add_action('plugins_loaded', 'mkwa_init_member_score');

// Your existing action hooks
add_action('init', function() {
    // [Your existing init code remains the same...]
});

add_action('mkwa_activity_logged', function($member_id) {
    // [Your existing activity_logged code remains the same...]
});

// Initialize frontend and AJAX
new MKWA_Frontend();
new MKWA_Ajax();

/**
 * Main plugin class
 */
final class MKWA_Fitness {
    // [Your existing class properties remain the same...]

    public function activate() {
        try {
            global $wpdb;
            $charset_collate = $wpdb->get_charset_collate();
            mkwa_log('Starting plugin activation...');

            // Your existing tables
            $sql_members = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mkwa_members (
                member_id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                current_level int(11) NOT NULL DEFAULT 1,
                total_points int(11) NOT NULL DEFAULT 0,
                created_at datetime NOT NULL DEFAULT '" . MKWA_CURRENT_TIME . "',
                PRIMARY KEY  (member_id),
                KEY user_id (user_id)
            ) $charset_collate;";

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

            // New tables for member metrics and leaderboard
            $sql_member_metrics = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mkwa_member_metrics (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                attendance_rate decimal(5,2) DEFAULT 0.00,
                challenge_completion_rate decimal(5,2) DEFAULT 0.00,
                community_participation_score decimal(5,2) DEFAULT 0.00,
                streak_score decimal(5,2) DEFAULT 0.00,
                point_earning_velocity decimal(8,2) DEFAULT 0.00,
                consistency_factor decimal(5,2) DEFAULT 0.00,
                engagement_depth decimal(5,2) DEFAULT 0.00,
                overall_score decimal(10,2) DEFAULT 0.00,
                last_calculated datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY user_idx (user_id),
                KEY score_idx (overall_score DESC)
            ) $charset_collate;";

            $sql_leaderboard_current = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mkwa_leaderboard_current (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                total_points bigint(20) NOT NULL DEFAULT 0,
                monthly_points int(11) NOT NULL DEFAULT 0,
                quarterly_points int(11) NOT NULL DEFAULT 0,
                ranking_score decimal(10,2) NOT NULL DEFAULT 0.00,
                monthly_rank int(11) DEFAULT NULL,
                quarterly_rank int(11) DEFAULT NULL,
                overall_rank int(11) DEFAULT NULL,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY user_idx (user_id),
                KEY ranking_idx (ranking_score DESC)
            ) $charset_collate;";

            $sql_leaderboard_history = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mkwa_leaderboard_history (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                period_type varchar(20) NOT NULL,
                period_start date NOT NULL,
                period_end date NOT NULL,
                points int(11) NOT NULL DEFAULT 0,
                final_rank int(11) NOT NULL,
                ranking_score decimal(10,2) NOT NULL DEFAULT 0.00,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY period_idx (period_type, period_start, period_end),
                KEY user_period_idx (user_id, period_type, period_start)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            mkwa_log('Creating database tables...');
            dbDelta($sql_members);
            dbDelta($sql_badges);
            dbDelta($sql_activity_log);
            dbDelta($sql_member_metrics);
            dbDelta($sql_leaderboard_current);
            dbDelta($sql_leaderboard_history);

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

    // [All your other existing methods remain the same...]
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