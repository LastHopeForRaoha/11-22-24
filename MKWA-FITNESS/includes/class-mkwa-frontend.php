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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function enqueue_scripts() {
        wp_enqueue_style(
            'mkwa-frontend',
            MKWA_PLUGIN_URL . 'templates/css/mkwa-frontend.css',
            array(),
            MKWA_VERSION
        );

        wp_enqueue_script(
            'mkwa-frontend',
            MKWA_PLUGIN_URL . 'templates/js/mkwa-frontend.js',
            array('jquery'),
            MKWA_VERSION,
            true
        );

        wp_localize_script('mkwa-frontend', 'mkwaAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mkwa-frontend-nonce')
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
}