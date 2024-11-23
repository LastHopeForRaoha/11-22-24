<?php
/**
 * Frontend Display Handler
 *
 * @package MkwaFitness
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKWA_Frontend {
    public function __construct() {
        add_shortcode('mkwa_dashboard', array($this, 'render_dashboard'));
        add_shortcode('mkwa_class_schedule', array($this, 'render_class_schedule'));
        add_shortcode('mkwa_leaderboard', array($this, 'display_leaderboard')); // Added leaderboard shortcode
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add theme support
        add_action('init', array($this, 'init_theme'));
    }

    /**
     * Initialize theme support
     */
    public function init_theme() {
        // Ensure theme directory exists
        $theme_dir = MKWA_PLUGIN_DIR . 'templates/css';
        if (!file_exists($theme_dir)) {
            wp_mkdir_p($theme_dir);
        }

        // Create theme file if it doesn't exist
        $theme_file = $theme_dir . '/mkwa-theme.css';
        if (!file_exists($theme_file)) {
            $this->create_default_theme_file($theme_file);
        }
    }

    /**
     * Create default theme file
     */
    private function create_default_theme_file($file_path) {
        $theme_css = "/* Mkwa Fitness Theme Variables */
:root {
    --mkwa-primary: #2C5530;      /* Deep forest green - represents growth */
    --mkwa-secondary: #8B4513;    /* Saddle brown - represents wood/earth */
    --mkwa-accent: #1B3F8B;       /* Deep water blue - represents water/wisdom */
    --mkwa-earth: #D2691E;        /* Earth orange - represents community/gathering */
    --mkwa-light: #F5F5DC;        /* Natural beige - represents birch bark */
    --mkwa-dark: #2F4F4F;         /* Dark slate - represents stone */
    --mkwa-success: #567D46;      /* Forest success - softer green */
    --mkwa-error: #8B4513;        /* Earth red - warmer take on traditional red */
    --mkwa-shadow: rgba(0,0,0,0.15);
}

/* Theme Overrides */
.mkwa-dashboard-header {
    background: var(--mkwa-light);
    border-bottom: 3px solid var(--mkwa-secondary);
}

.mkwa-level {
    background: var(--mkwa-primary);
    color: var(--mkwa-light);
}

.mkwa-dashboard-section {
    border-top: 4px solid var(--mkwa-primary);
    background: white;
}

.mkwa-dashboard-section h3 {
    color: var(--mkwa-primary);
    border-bottom: 2px solid var(--mkwa-secondary);
}

.mkwa-stat-block {
    background: var(--mkwa-light);
    border: 1px solid var(--mkwa-secondary);
}

.mkwa-progress-fill {
    background: linear-gradient(to right, var(--mkwa-primary), var(--mkwa-secondary));
}

.mkwa-activity-points {
    color: var(--mkwa-success);
}

.mkwa-badge-item {
    background: var(--mkwa-light);
    border: 1px solid var(--mkwa-secondary);
}

.mkwa-filter-btn {
    background-color: var(--mkwa-light);
    border: 2px solid var(--mkwa-secondary);
    color: var(--mkwa-dark);
}

.mkwa-filter-btn.active {
    background-color: var(--mkwa-primary);
    border-color: var(--mkwa-primary);
    color: var(--mkwa-light);
}

.mkwa-class-card {
    background: white;
    border-top: 4px solid var(--mkwa-primary);
}

.mkwa-class-card.registered {
    border: 2px solid var(--mkwa-success);
    background: linear-gradient(to bottom right, white, rgba(86, 125, 70, 0.1));
}

.mkwa-btn-register {
    background-color: var(--mkwa-primary);
    color: var(--mkwa-light);
}

.mkwa-btn-register:hover {
    background-color: var(--mkwa-secondary);
}

.mkwa-btn-cancel {
    background-color: var(--mkwa-error);
    color: var(--mkwa-light);
}

.mkwa-notification-success {
    background-color: var(--mkwa-success);
}

.mkwa-notification-error {
    background-color: var(--mkwa-error);
}

/* Biophilic Design Elements */
.mkwa-dashboard-section,
.mkwa-class-card {
    position: relative;
    overflow: hidden;
}

.mkwa-dashboard-section::before,
.mkwa-class-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(to right, var(--mkwa-primary), var(--mkwa-secondary));
    opacity: 0.7;
}";
        
        file_put_contents($file_path, $theme_css);
    }

    public function enqueue_scripts() {
        // Enqueue Theme CSS first
        wp_enqueue_style(
            'mkwa-theme',
            MKWA_PLUGIN_URL . 'templates/css/mkwa-theme.css',
            array(),
            MKWA_VERSION
        );

        // Enqueue Main CSS
        wp_enqueue_style(
            'mkwa-frontend',
            MKWA_PLUGIN_URL . 'templates/css/mkwa-frontend.css',
            array('mkwa-theme'),
            MKWA_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'mkwa-frontend',
            MKWA_PLUGIN_URL . 'templates/js/mkwa-frontend.js',
            array('jquery'),
            MKWA_VERSION,
            true
        );

        // AJAX configuration
        wp_localize_script('mkwa-frontend', 'mkwaAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mkwa-frontend-nonce')
        ));

        // Localized strings for JavaScript
        wp_localize_script('mkwa-frontend', 'mkwaStrings', array(
            'registering' => __('Registering...', 'mkwa-fitness'),
            'cancelling' => __('Cancelling...', 'mkwa-fitness'),
            'register' => __('Register Now', 'mkwa-fitness'),
            'cancelRegistration' => __('Cancel Registration', 'mkwa-fitness'),
            'confirmCancel' => __('Are you sure you want to cancel your registration?', 'mkwa-fitness'),
            'errorOccurred' => __('An error occurred. Please try again.', 'mkwa-fitness')
        ));
    }

    public function render_dashboard() {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to view your fitness dashboard.', 'mkwa-fitness') . '</p>';
        }

        $user_id = get_current_user_id();
        $member_id = mkwa_get_member_id($user_id);
        
        if (!$member_id) {
            $member_id = mkwa_ensure_member($user_id);
        }

        if (!$member_id) {
            return '<p>' . esc_html__('Error loading member data.', 'mkwa-fitness') . '</p>';
        }

        ob_start();
        include MKWA_PLUGIN_DIR . 'templates/dashboard/main.php';
        return ob_get_clean();
    }

    /**
     * Render class schedule shortcode
     */
    public function render_class_schedule() {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to view and register for classes.', 'mkwa-fitness') . '</p>';
        }

        $args = array(
            'post_type' => 'mkwa_class',
            'posts_per_page' => -1,
            'meta_key' => '_mkwa_class_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => '_mkwa_class_date',
                    'value' => date('Y-m-d'),
                    'compare' => '>=',
                    'type' => 'DATE'
                )
            )
        );

        $classes = new WP_Query($args);
        
        ob_start();
        ?>
        <div class="mkwa-class-schedule">
            <div class="mkwa-class-filters">
                <button class="mkwa-filter-btn active" data-filter="all">
                    <?php esc_html_e('All Classes', 'mkwa-fitness'); ?>
                </button>
                <button class="mkwa-filter-btn" data-filter="registered">
                    <?php esc_html_e('My Classes', 'mkwa-fitness'); ?>
                </button>
            </div>

            <div class="mkwa-class-grid">
                <?php
                if ($classes->have_posts()) {
                    while ($classes->have_posts()) {
                        $classes->the_post();
                        $class_id = get_the_ID();
                        $attendees = get_post_meta($class_id, '_mkwa_attendees', true) ?: array();
                        $capacity = get_post_meta($class_id, '_mkwa_capacity', true);
                        $is_registered = in_array(get_current_user_id(), $attendees);
                        $is_full = count($attendees) >= $capacity;
                        
                        include MKWA_PLUGIN_DIR . 'templates/class-card.php';
                    }
                } else {
                    echo '<p class="mkwa-no-classes">' . 
                         esc_html__('No upcoming classes scheduled.', 'mkwa-fitness') . 
                         '</p>';
                }
                wp_reset_postdata();
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Display leaderboard
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function display_leaderboard($atts) {
        global $mkwa_leaderboard;
        
        if (!isset($mkwa_leaderboard)) {
            return '<p>' . esc_html__('Leaderboard system is not initialized.', 'mkwa-fitness') . '</p>';
        }
        
        $atts = shortcode_atts(array(
            'type' => 'overall',
            'limit' => 10
        ), $atts);

        $leaderboard_data = $mkwa_leaderboard->get_leaderboard($atts['type'], $atts['limit']);
        
        if (empty($leaderboard_data)) {
            return '<p>' . esc_html__('No leaderboard data available.', 'mkwa-fitness') . '</p>';
        }

        ob_start();
        ?>
        <div class="mkwa-leaderboard">
            <h2><?php echo esc_html(ucfirst($atts['type']) . ' ' . __('Leaderboard', 'mkwa-fitness')); ?></h2>
            <table class="mkwa-leaderboard-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Rank', 'mkwa-fitness'); ?></th>
                        <th><?php esc_html_e('Name', 'mkwa-fitness'); ?></th>
                        <th><?php esc_html_e('Points', 'mkwa-fitness'); ?></th>
                        <th><?php esc_html_e('Score', 'mkwa-fitness'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leaderboard_data as $entry): ?>
                    <tr<?php echo ($entry->user_id === get_current_user_id()) ? ' class="current-user"' : ''; ?>>
                        <td><?php echo esc_html($entry->rank); ?></td>
                        <td><?php echo esc_html($entry->display_name); ?></td>
                        <td><?php echo esc_html(number_format($entry->total_points)); ?></td>
                        <td><?php echo esc_html(number_format($entry->ranking_score, 1)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
         return ob_get_clean();
    }

    /**
     * Format number with abbreviation for thousands/millions
     *
     * @param int $number Number to format
     * @return string Formatted number
     */
    private function format_number($number) {
        if ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        }
        if ($number >= 1000) {
            return round($number / 1000, 1) . 'K';
        }
        return $number;
    }
}