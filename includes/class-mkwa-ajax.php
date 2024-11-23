<?php
/**
 * AJAX Handler Class
 *
 * @package MkwaFitness
 */

if (!defined('ABSPATH')) {
    exit;
}

class MKWA_Ajax {
    
    public function __construct() {
        // Existing handlers
        add_action('wp_ajax_mkwa_refresh_activity', array($this, 'refresh_activity_log'));
        add_action('wp_ajax_mkwa_refresh_progress', array($this, 'refresh_progress'));
        add_action('wp_ajax_mkwa_log_activity', array($this, 'log_activity'));
        
        // New class handlers
        add_action('wp_ajax_mkwa_register_class', array($this, 'register_class'));
        add_action('wp_ajax_mkwa_cancel_class', array($this, 'cancel_class'));
        add_action('wp_ajax_mkwa_refresh_classes', array($this, 'refresh_classes'));
    }

    /**
     * Refresh activity log
     */
    public function refresh_activity_log() {
        try {
            if (!check_ajax_referer('mkwa-frontend-nonce', 'nonce', false)) {
                throw new Exception(__('Invalid security token.', 'mkwa-fitness'));
            }

            $user_id = get_current_user_id();
            if (!$user_id) {
                throw new Exception(__('User not logged in.', 'mkwa-fitness'));
            }

            global $wpdb;
            $activities = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}mkwa_activity_log 
                WHERE user_id = %d 
                ORDER BY logged_at DESC 
                LIMIT 10",
                $user_id
            ));

            ob_start();
            include MKWA_PLUGIN_DIR . 'templates/dashboard/activity-log.php';
            $html = ob_get_clean();

            wp_send_json_success(array(
                'html' => $html
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Refresh progress data
     */
    public function refresh_progress() {
        try {
            if (!check_ajax_referer('mkwa-frontend-nonce', 'nonce', false)) {
                throw new Exception(__('Invalid security token.', 'mkwa-fitness'));
            }

            $user_id = get_current_user_id();
            if (!$user_id) {
                throw new Exception(__('User not logged in.', 'mkwa-fitness'));
            }

            $member_id = mkwa_get_member_id($user_id);
            if (!$member_id) {
                throw new Exception(__('Member not found.', 'mkwa-fitness'));
            }

            $member_stats = mkwa_get_member_stats($member_id);

            ob_start();
            include MKWA_PLUGIN_DIR . 'templates/dashboard/progress-tracker.php';
            $html = ob_get_clean();

            wp_send_json_success(array(
                'html' => $html,
                'stats' => array(
                    'total_points' => $member_stats['total_points'],
                    'current_level' => mkwa_calculate_level($member_stats['total_points']),
                    'current_streak' => $member_stats['current_streak'],
                    'total_activities' => $member_stats['total_activities']
                )
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Log new activity
     */
    public function log_activity() {
        try {
            if (!check_ajax_referer('mkwa-frontend-nonce', 'nonce', false)) {
                throw new Exception(__('Invalid security token.', 'mkwa-fitness'));
            }

            $user_id = get_current_user_id();
            if (!$user_id) {
                throw new Exception(__('User not logged in.', 'mkwa-fitness'));
            }

            $activity_type = isset($_POST['activity_type']) ? sanitize_text_field($_POST['activity_type']) : '';
            if (!$activity_type) {
                throw new Exception(__('Activity type is required.', 'mkwa-fitness'));
            }

            $member_id = mkwa_get_member_id($user_id);
            if (!$member_id) {
                throw new Exception(__('Member not found.', 'mkwa-fitness'));
            }

            global $wpdb;
            $result = $wpdb->insert(
                $wpdb->prefix . 'mkwa_activity_log',
                array(
                    'user_id' => $user_id,
                    'activity_type' => $activity_type,
                    'points' => mkwa_get_points_for_activity($activity_type),
                    'logged_at' => current_time('mysql')
                ),
                array('%d', '%s', '%d', '%s')
            );

            if ($result === false) {
                throw new Exception(__('Failed to log activity.', 'mkwa-fitness'));
            }

            do_action('mkwa_activity_logged', $member_id);

            wp_send_json_success(array(
                'message' => __('Activity logged successfully!', 'mkwa-fitness')
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Register for a class
     */
    public function register_class() {
        try {
            if (!check_ajax_referer('mkwa-frontend-nonce',