<?php
/**
 * Prize Claim Core Class
 * Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-02-20 01:37:34
 * Current User's Login: EvThatGuy
 */

if (!defined('ABSPATH')) {
    exit;
}

class PrizeClaim {
    private $version;
    private $db;
    private $forms;
    private $notify;
    private $admin;
    private $shortcodes;

    public function __construct() {
        $this->version = PC_VERSION;
        $this->load_classes();
    }

    private function load_classes() {
        $this->db = new PrizeClaimDatabase();
        $this->forms = new PrizeClaimForms();
        $this->notify = new PrizeClaimNotify();
        $this->shortcodes = new PrizeClaimShortcodes();
        
        if (is_admin()) {
            $this->admin = new PrizeClaimAdmin();
        }
    }

    public function init() {
        // Core hooks
        add_action('wp_enqueue_scripts', array($this, 'load_scripts'));
        add_action('init', array($this, 'register_post_types'));
        
        // AJAX handlers
        add_action('wp_ajax_pc_submit_claim', array($this, 'handle_claim_submission'));
        add_action('wp_ajax_pc_get_claim_status', array($this, 'get_claim_status'));
    }

    public function load_scripts() {
        // Main styles
        wp_enqueue_style('pc-style', PC_URL . 'public/css/style.css', array(), $this->version);
        
        // Main script
        wp_enqueue_script('pc-script', PC_URL . 'public/js/script.js', array('jquery'), $this->version, true);
        
        // Get verification statuses for current user
        $verification_status = array(
            'w9_verified' => false,
            'dd_verified' => false
        );

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $verification_status['w9_verified'] = $this->db->get_w9_verification_status($user_id) === 'verified';
            $verification_status['dd_verified'] = $this->db->get_direct_deposit_status($user_id) === 'verified';
        }
        
        // Localize script
        wp_localize_script('pc-script', 'pcAjax', array(
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pc-nonce'),
            'homeUrl' => home_url(),
            'isAdmin' => current_user_can('manage_options'),
            'w9Amount' => $this->get_w9_required_amount(),
            'w9FormUrl' => $this->get_w9_form_url(),
            'ddFormUrl' => $this->get_direct_deposit_form_url(),
            'verificationStatus' => $verification_status
        ));
    }

    public function handle_claim_submission() {
        check_ajax_referer('pc-nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }

        $user_id = get_current_user_id();
        $amount = floatval($_POST['amount']);
        $tournament_name = sanitize_text_field($_POST['tournament_name']);

        // Check if W9 is required based on amount
        $w9_required = $amount >= $this->get_w9_required_amount();
        
        // Get existing verification statuses
        $w9_status = $this->db->get_w9_verification_status($user_id);
        $dd_status = $this->db->get_direct_deposit_status($user_id);

        // Check if forms are needed
        $needs_w9 = $w9_required && $w9_status !== 'verified';
        $needs_dd = $dd_status !== 'verified';
        // If either form is needed, return error with requirements
        if ($needs_w9 || $needs_dd) {
            $requirements = [];
            $forms = [];

            if ($needs_w9) {
                $requirements[] = 'W9 form verification';
                $forms['w9'] = $this->get_w9_form_url();
            }
            if ($needs_dd) {
                $requirements[] = 'direct deposit verification';
                $forms['dd'] = $this->get_direct_deposit_form_url();
            }

            wp_send_json_error([
                'message' => 'Please complete the following before submitting a claim: ' . implode(' and ', $requirements),
                'requirements' => [
                    'needs_w9' => $needs_w9,
                    'needs_dd' => $needs_dd
                ],
                'forms' => $forms
            ]);
            return;
        }

        // All verifications passed, create claim
        $claim_data = array(
            'user_id' => $user_id,
            'tournament_name' => $tournament_name,
            'amount' => $amount,
            'submission_date' => current_time('mysql'),
            'last_updated' => current_time('mysql'),
            'claim_number' => $this->generate_claim_number(),
            'w9_status' => $w9_required ? 'verified' : 'not_required',
            'direct_deposit_status' => 'verified'
        );

        $claim_id = $this->db->create_claim($claim_data);
        
        if ($claim_id) {
            do_action('pc_claim_submitted', $claim_id, $user_id);
            wp_send_json_success(array(
                'claim_id' => $claim_id,
                'claim_number' => $claim_data['claim_number'],
                'status' => 'processing'
            ));
        } else {
            wp_send_json_error('Failed to create claim');
        }
    }

    public function get_claim_status() {
        check_ajax_referer('pc-nonce', 'nonce');
        
        $claim_id = intval($_POST['claim_id']);
        $user_id = get_current_user_id();
        
        $claim = $this->db->get_claim($claim_id);
        
        if (!$claim || $claim->user_id != $user_id) {
            wp_send_json_error('Invalid claim');
        }
        
        wp_send_json_success($claim);
    }

    private function generate_claim_number() {
        global $wpdb;
        do {
            $claim_number = 'PC' . date('Y') . mt_rand(1000, 9999);
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}prize_claims WHERE claim_number = %s",
                $claim_number
            ));
        } while ($exists);
        
        return $claim_number;
    }

    private function get_w9_required_amount() {
        return floatval($this->db->get_setting('w9_required_amount', 600.00));
    }

    private function get_w9_form_url() {
        return $this->db->get_setting('w9_form_url', 'https://nextform.app/form/w9Mar2024/1ndeijALNwy8RXzWvQnGkX');
    }

    private function get_direct_deposit_form_url() {
        return $this->db->get_setting('direct_deposit_form_url', 'https://nextform.app/form/dd2024/default');
    }

    /**
     * Register post types if needed
     * This is a placeholder for future functionality
     */
    public function register_post_types() {
        // No post types needed currently
        // Left as placeholder for future use
    }
}