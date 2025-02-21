<?php
/**
 * Prize Claim AJAX Handlers
 * Current UTC Time: 2025-02-20 00:06:20
 * Current User: EvThatGuy
 */

if (!defined('ABSPATH')) {
    exit;
}

class PrizeClaimAjaxHandlers {
    private $db;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;

        // Public AJAX actions
        add_action('wp_ajax_pc_submit_claim', array($this, 'handle_claim_submission'));
        add_action('wp_ajax_pc_get_claim_status', array($this, 'get_claim_status'));
        add_action('wp_ajax_pc_get_user_claims', array($this, 'get_user_claims'));
        
        // Admin AJAX actions
        add_action('wp_ajax_pc_admin_verify_w9', array($this, 'handle_w9_verification'));
        add_action('wp_ajax_pc_admin_process_payment', array($this, 'handle_payment_processing'));
        add_action('wp_ajax_pc_admin_close_claim', array($this, 'handle_claim_closure'));
        add_action('wp_ajax_pc_admin_bulk_action', array($this, 'handle_bulk_action'));
        add_action('wp_ajax_pc_admin_get_claim_details', array($this, 'get_claim_details'));
    }

    /**
     * Handle new claim submission
     */
    public function handle_claim_submission() {
        check_ajax_referer('pc-public-nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('User must be logged in');
        }

        $claim_data = array(
            'user_id' => get_current_user_id(),
            'tournament_name' => sanitize_text_field($_POST['tournament_name']),
            'amount' => floatval($_POST['amount']),
            'claim_number' => $this->generate_claim_number(),
            'submission_date' => current_time('mysql'),
            'last_updated' => current_time('mysql'),
            'status' => 'pending',
            'w9_status' => 'pending'
        );

        // Validate amount
        $min_amount = get_option('pc_minimum_payout_amount', 10);
        if ($claim_data['amount'] < $min_amount) {
            wp_send_json_error(sprintf(
                __('Minimum claim amount is $%s', 'prize-claim'),
                number_format($min_amount, 2)
            ));
        }

        // Insert claim
        $result = $this->db->insert(
            $this->db->prefix . 'prize_claims',
            $claim_data,
            array('%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s')
        );

        if (!$result) {
            wp_send_json_error('Failed to create claim');
        }

        $claim_id = $this->db->insert_id;

        // Log activity
        $this->log_activity($claim_id, 'create', 'Claim submitted');

        // Send notification
        do_action('pc_claim_submitted', $claim_id, $claim_data);

        wp_send_json_success(array(
            'claim_id' => $claim_id,
            'claim_number' => $claim_data['claim_number'],
            'needs_w9' => $claim_data['amount'] >= get_option('pc_w9_required_amount', 600)
        ));
    }

    /**
     * Admin: Verify W9 manually
     */
    public function handle_w9_verification() {
        check_ajax_referer('pc-admin-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $user_id = intval($_POST['user_id']);
        $notes = sanitize_textarea_field($_POST['verification_notes']);
        $current_time = current_time('mysql');

        // Insert W9 verification record
        $result = $this->db->insert(
            $this->db->prefix . 'prize_claim_w9_status',
            array(
                'user_id' => $user_id,
                'verified_by' => get_current_user_id(),
                'verification_date' => $current_time,
                'notes' => $notes,
                'status' => 'verified',
                'expiration_date' => date('Y-m-d H:i:s', strtotime('+1 year', strtotime($current_time)))
            )
        );

        if (!$result) {
            wp_send_json_error('Failed to save W9 verification');
        }

        // Update all pending claims for this user
        $this->db->update(
            $this->db->prefix . 'prize_claims',
            array(
                'w9_status' => 'verified',
                'last_updated' => $current_time
            ),
            array(
                'user_id' => $user_id,
                'w9_status' => 'pending'
            )
        );

        // Log activity
        $affected_claims = $this->db->get_col($this->db->prepare(
            "SELECT id FROM {$this->db->prefix}prize_claims 
             WHERE user_id = %d AND w9_status = 'verified'",
            $user_id
        ));

        foreach ($affected_claims as $claim_id) {
            $this->log_activity(
                $claim_id,
                'w9_verify',
                sprintf(
                    'W9 verified by admin (ID: %d). Notes: %s',
                    get_current_user_id(),
                    $notes
                )
            );
        }

        do_action('pc_w9_verified', $user_id);
        wp_send_json_success();
    }

    /**
     * Admin: Process payment
     */
    public function handle_payment_processing() {
        check_ajax_referer('pc-admin-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $claim_id = intval($_POST['claim_id']);
        $payment_date = sanitize_text_field($_POST['payment_date']);
        $notes = sanitize_textarea_field($_POST['payment_notes']);
        $current_time = current_time('mysql');

        // Update claim with payment info
        $result = $this->db->update(
            $this->db->prefix . 'prize_claims',
            array(
                'status' => 'completed',
                'payment_date' => $payment_date,
                'payment_notes' => $notes,
                'last_updated' => $current_time
            ),
            array('id' => $claim_id)
        );

        if (!$result) {
            wp_send_json_error('Failed to update payment status');
        }

        $this->log_activity(
            $claim_id,
            'payment_scheduled',
            sprintf(
                'Payment scheduled for %s. Notes: %s',
                $payment_date,
                $notes
            )
        );

        do_action('pc_payment_scheduled', $claim_id, $payment_date);
        wp_send_json_success();
    }

    /**
     * Admin: Close claim
     */
    public function handle_claim_closure() {
        check_ajax_referer('pc-admin-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $claim_id = intval($_POST['claim_id']);
        $reason = sanitize_text_field($_POST['closure_reason']);
        $notes = sanitize_textarea_field($_POST['closure_notes']);
        $current_time = current_time('mysql');

        $result = $this->db->update(
            $this->db->prefix . 'prize_claims',
            array(
                'status' => 'closed',
                'closure_reason' => $reason,
                'closure_notes' => $notes,
                'last_updated' => $current_time
            ),
            array('id' => $claim_id)
        );

        if (!$result) {
            wp_send_json_error('Failed to close claim');
        }

        $this->log_activity(
            $claim_id,
            'claim_closed',
            sprintf(
                'Claim closed. Reason: %s. Notes: %s',
                $reason,
                $notes
            )
        );

        do_action('pc_claim_closed', $claim_id, $reason);
        wp_send_json_success();
    }

    /**
     * Get claim details
     */
    public function get_claim_details() {
        check_ajax_referer('pc-admin-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $claim_id = intval($_POST['claim_id']);
        
        $claim = $this->db->get_row($this->db->prepare(
            "SELECT c.*, u.display_name as user_name, u.user_email
             FROM {$this->db->prefix}prize_claims c
             LEFT JOIN {$this->db->users} u ON c.user_id = u.ID
             WHERE c.id = %d",
            $claim_id
        ));

        if (!$claim) {
            wp_send_json_error('Claim not found');
        }

        wp_send_json_success(array('claim' => $claim));
    }

    /**
     * Generate unique claim number
     */
    private function generate_claim_number() {
        $prefix = 'PC';
        $year = date('Y');
        $random = strtoupper(substr(uniqid(), -5));
        return sprintf('%s-%s-%s', $prefix, $year, $random);
    }

    /**
     * Log activity
     */
    private function log_activity($claim_id, $action, $description) {
        $this->db->insert(
            $this->db->prefix . 'prize_claim_activity_log',
            array(
                'claim_id' => $claim_id,
                'user_id' => get_current_user_id(),
                'action' => $action,
                'description' => $description,
                'created_at' => current_time('mysql'),
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            )
        );
    }
}

// Initialize handlers
new PrizeClaimAjaxHandlers();