<?php
/**
 * Prize Claim Admin Interface
 * Current UTC Time: 2025-02-19 22:26:31
 * Current User: EvThatGuy
 */

if (!defined('ABSPATH')) {
    exit;
}

class PrizeClaimAdmin {
    private $current_tab;
    private $tabs;
    private $db;

    public function __construct() {
        // Initialize database connection
        global $wpdb;
        $this->db = $wpdb;

        // Set current tab
        $this->current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'pending';
        
        // Define available tabs
        $this->tabs = array(
            'pending' => __('Pending Claims', 'prize-claim'),
            'processing' => __('Processing', 'prize-claim'),
            'completed' => __('Completed Claims', 'prize-claim'),
            'closed' => __('Closed Claims', 'prize-claim')
        );

        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_pc_update_claim_status', array($this, 'update_claim_status'));
        add_action('wp_ajax_pc_verify_w9', array($this, 'verify_w9'));
        add_action('wp_ajax_pc_verify_direct_deposit', array($this, 'verify_direct_deposit'));
        add_action('wp_ajax_pc_process_payment', array($this, 'process_payment'));
        add_action('wp_ajax_pc_close_claim', array($this, 'close_claim'));
        add_action('wp_ajax_pc_bulk_action', array($this, 'handle_bulk_action'));
        add_action('wp_ajax_pc_export_claims', array($this, 'export_claims'));
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Prize Claims', 'prize-claim'),
            __('Prize Claims', 'prize-claim'),
            'manage_options',
            'prize-claims',
            array($this, 'render_admin_page'),
            'dashicons-awards',
            30
        );

        add_submenu_page(
            'prize-claims',
            __('Settings', 'prize-claim'),
            __('Settings', 'prize-claim'),
            'manage_options',
            'prize-claims-settings',
            array($this, 'render_settings_page')
        );
    }
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'prize-claim'));
        }

        // Get claims based on filters
        $claims = $this->get_filtered_claims();
        
        // Include the template
        include PC_DIR . '/admin/claims-list.php';
    }

    private function get_filtered_claims() {
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : $this->current_tab;
        $date_range = isset($_GET['date_range']) ? sanitize_text_field($_GET['date_range']) : '30days';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        // Start building query
        $query = "SELECT c.*, u.display_name as user_name 
                 FROM {$this->db->prefix}prize_claims c
                 LEFT JOIN {$this->db->users} u ON c.user_id = u.ID
                 WHERE 1=1";

        $where_parts = array();
        $query_args = array();

        // Status filter
        if ($status && $status !== 'all') {
            $where_parts[] = "c.status = %s";
            $query_args[] = $status;
        }

        // Date range filter
        switch ($date_range) {
            case '7days':
                $where_parts[] = "c.submission_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case '30days':
                $where_parts[] = "c.submission_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case '90days':
                $where_parts[] = "c.submission_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
                break;
        }

        // Search filter
        if ($search) {
            $where_parts[] = "(c.claim_number LIKE %s OR c.tournament_name LIKE %s OR u.display_name LIKE %s)";
            $search_wild = '%' . $this->db->esc_like($search) . '%';
            $query_args[] = $search_wild;
            $query_args[] = $search_wild;
            $query_args[] = $search_wild;
        }

        // Add where clause if we have conditions
        if (!empty($where_parts)) {
            $query .= " AND " . implode(" AND ", $where_parts);
        }

        // Add order
        $query .= " ORDER BY c.submission_date DESC";

        // Prepare and execute query if we have arguments
        if (!empty($query_args)) {
            $query = $this->db->prepare($query, $query_args);
        }

        return $this->db->get_results($query);
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'prize-claims') === false) {
            return;
        }

        wp_enqueue_style('pc-admin-css', PC_URL . 'admin/css/admin.css', array(), PC_VERSION);
        wp_enqueue_script('pc-admin-js', PC_URL . 'admin/js/admin.js', array('jquery'), PC_VERSION, true);
        
        wp_localize_script('pc-admin-js', 'pcAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pc-admin-nonce'),
            'w9Amount' => get_option('pc_w9_required_amount', 600),
            'strings' => array(
                'confirmVerify' => __('Are you sure you want to verify this W9?', 'prize-claim'),
                'confirmClose' => __('Are you sure you want to close this claim?', 'prize-claim'),
                'processing' => __('Processing...', 'prize-claim'),
                'success' => __('Success!', 'prize-claim'),
                'error' => __('Error occurred.', 'prize-claim')
            )
        ));
    }
    public function verify_w9() {
        check_ajax_referer('pc-admin-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $user_id = intval($_POST['user_id']);
        $notes = sanitize_textarea_field($_POST['verification_notes']);

        // Insert W9 verification record
        $result = $this->db->insert(
            $this->db->prefix . 'prize_claim_w9_status',
            array(
                'user_id' => $user_id,
                'verified_by' => get_current_user_id(),
                'verification_date' => current_time('mysql'),
                'notes' => $notes
            )
        );

        if ($result) {
            // Update all pending claims for this user
            $this->db->update(
                $this->db->prefix . 'prize_claims',
                array('w9_status' => 'verified'),
                array('user_id' => $user_id, 'w9_status' => 'pending')
            );

            do_action('pc_w9_verified', $user_id);
            wp_send_json_success();
        }

        wp_send_json_error('Failed to verify W9');
    }

    public function verify_direct_deposit() {
        check_ajax_referer('pc-admin-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $claim_id = intval($_POST['claim_id']);
        $bank_name = sanitize_text_field($_POST['bank_name']);
        $account_type = sanitize_text_field($_POST['account_type']);
        $routing_last4 = sanitize_text_field($_POST['routing_last4']);
        $account_last4 = sanitize_text_field($_POST['account_last4']);

        // Update banking info status
        $result = $this->db->update(
            $this->db->prefix . 'prize_claim_banking',
            array(
                'status' => 'verified',
                'verification_date' => current_time('mysql'),
                'verified_by' => get_current_user_id(),
                'bank_name' => $bank_name,
                'account_type' => $account_type,
                'routing_last4' => $routing_last4,
                'account_last4' => $account_last4
            ),
            array('claim_id' => $claim_id)
        );

        if ($result) {
            // Update claim status
            $this->db->update(
                $this->db->prefix . 'prize_claims',
                array('direct_deposit_status' => 'verified'),
                array('id' => $claim_id)
            );

            $this->check_claim_ready($claim_id);
            wp_send_json_success();
        }

        wp_send_json_error('Failed to verify direct deposit');
    }

    public function process_payment() {
        check_ajax_referer('pc-admin-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $claim_id = intval($_POST['claim_id']);
        $amount = floatval($_POST['payment_amount']);
        $date = sanitize_text_field($_POST['payment_date']);
        $notes = sanitize_textarea_field($_POST['payment_notes']);

         // Update claim with payment info
         $result = $this->db->update(
            $this->db->prefix . 'prize_claims',
            array(
                'status' => 'completed',
                'payment_date' => $date,
                'payment_amount' => $amount,
                'payment_notes' => $notes,
                'last_updated' => current_time('mysql')
            ),
            array('id' => $claim_id)
        );

        if ($result) {
            do_action('pc_payment_processed', $claim_id, $amount, $date);
            wp_send_json_success();
        }

        wp_send_json_error('Failed to process payment');
    }

    public function close_claim() {
        check_ajax_referer('pc-admin-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $claim_id = intval($_POST['claim_id']);
        $reason = sanitize_text_field($_POST['closure_reason']);
        $notes = sanitize_textarea_field($_POST['closure_notes']);

        $result = $this->db->update(
            $this->db->prefix . 'prize_claims',
            array(
                'status' => 'closed',
                'closure_reason' => $reason,
                'closure_notes' => $notes,
                'last_updated' => current_time('mysql')
            ),
            array('id' => $claim_id)
        );

        if ($result) {
            do_action('pc_claim_closed', $claim_id, $reason);
            wp_send_json_success();
        }

        wp_send_json_error('Failed to close claim');
    }

    private function check_claim_ready($claim_id) {
        $claim = $this->get_claim($claim_id);

        if ($claim) {
            $w9_required = floatval($claim->amount) >= get_option('pc_w9_required_amount', 600);
            $w9_verified = $claim->w9_status === 'verified';
            $dd_verified = $claim->direct_deposit_status === 'verified';

            if ((!$w9_required || $w9_verified) && $dd_verified) {
                $this->db->update(
                    $this->db->prefix . 'prize_claims',
                    array(
                        'status' => 'processing',
                        'last_updated' => current_time('mysql')
                    ),
                    array('id' => $claim_id)
                );

                do_action('pc_claim_ready_for_payment', $claim_id);
            }
        }
    }

    private function get_claim($claim_id) {
        return $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->db->prefix}prize_claims WHERE id = %d",
            $claim_id
        ));
    }

    private function get_tab_count($tab) {
        return $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$this->db->prefix}prize_claims WHERE status = %s",
            $tab
        ));
    }

    private function show_admin_notice($message) {
        $type = 'success';
        $text = '';

        switch ($message) {
            case 'w9-verified':
                $text = __('W9 form has been verified successfully.', 'prize-claim');
                break;
            case 'dd-verified':
                $text = __('Direct deposit information has been verified successfully.', 'prize-claim');
                break;
            case 'payment-processed':
                $text = __('Payment has been processed successfully.', 'prize-claim');
                break;
            case 'claim-closed':
                $text = __('Claim has been closed successfully.', 'prize-claim');
                break;
            case 'error':
                $type = 'error';
                $text = __('An error occurred. Please try again.', 'prize-claim');
                break;
        }

        if ($text) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($type),
                esc_html($text)
            );
        }
    }
    public function handle_bulk_action() {
        check_ajax_referer('pc-admin-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $action = sanitize_text_field($_POST['bulk_action']);
        $claim_ids = array_map('intval', $_POST['claim_ids']);

        switch ($action) {
            case 'verify-w9':
                $this->bulk_verify_w9($claim_ids);
                break;
            case 'verify-dd':
                $this->bulk_verify_dd($claim_ids);
                break;
            case 'process-payment':
                $this->bulk_process_payment($claim_ids);
                break;
            case 'close':
                $this->bulk_close_claims($claim_ids);
                break;
            default:
                wp_send_json_error('Invalid bulk action');
        }

        wp_send_json_success();
    }

    private function bulk_verify_w9($claim_ids) {
        $user_ids = $this->db->get_col($this->db->prepare(
            "SELECT DISTINCT user_id FROM {$this->db->prefix}prize_claims WHERE id IN (" . 
            implode(',', array_fill(0, count($claim_ids), '%d')) . ")",
            $claim_ids
        ));

        foreach ($user_ids as $user_id) {
            $this->verify_w9(array(
                'user_id' => $user_id,
                'verification_notes' => __('Bulk verification', 'prize-claim')
            ));
        }
    }

    private function bulk_verify_dd($claim_ids) {
        foreach ($claim_ids as $claim_id) {
            $this->verify_direct_deposit(array(
                'claim_id' => $claim_id,
                'verification_notes' => __('Bulk verification', 'prize-claim')
            ));
        }
    }

    private function bulk_process_payment($claim_ids) {
        foreach ($claim_ids as $claim_id) {
            $claim = $this->get_claim($claim_id);
            if ($claim && $claim->status === 'processing') {
                $this->process_payment(array(
                    'claim_id' => $claim_id,
                    'payment_amount' => $claim->amount,
                    'payment_date' => current_time('Y-m-d'),
                    'payment_notes' => __('Bulk payment processing', 'prize-claim')
                ));
            }
        }
    }

    private function bulk_close_claims($claim_ids) {
        foreach ($claim_ids as $claim_id) {
            $this->close_claim(array(
                'claim_id' => $claim_id,
                'closure_reason' => 'other',
                'closure_notes' => __('Bulk closure', 'prize-claim')
            ));
        }
    }

    public function export_claims() {
        check_ajax_referer('pc-admin-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $claims = $this->get_filtered_claims();
        
        $csv_headers = array(
            'Claim #',
            'User',
            'Tournament',
            'Amount',
            'W9 Status',
            'Direct Deposit Status',
            'Status',
            'Submission Date',
            'Payment Date',
            'Payment Amount'
        );

        $csv_rows = array();
        foreach ($claims as $claim) {
            $csv_rows[] = array(
                $claim->claim_number,
                $claim->user_name,
                $claim->tournament_name,
                $claim->amount,
                $claim->w9_status,
                $claim->direct_deposit_status,
                $claim->status,
                $claim->submission_date,
                $claim->payment_date,
                $claim->payment_amount
            );
        }

        $filename = 'prize-claims-export-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $fp = fopen('php://output', 'w');
        fputcsv($fp, $csv_headers);
        foreach ($csv_rows as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);
        exit;
    }
} 