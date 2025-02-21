<?php
/**
 * Prize Claim Notification System
 * Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-02-20 01:47:17
 * Current User's Login: EvThatGuy
 */

if (!defined('ABSPATH')) {
    exit;
}

class PrizeClaimNotify {
    private $email_templates = array();
    
    public function __construct() {
        $this->setup_hooks();
        $this->init_email_templates();
    }

    private function setup_hooks() {
        // Notification triggers
        add_action('pc_claim_submitted', array($this, 'notify_claim_submitted'), 10, 2);
        add_action('pc_w9_status_changed', array($this, 'notify_w9_status'), 10, 3);
        add_action('pc_direct_deposit_status_changed', array($this, 'notify_dd_status'), 10, 3);
        add_action('pc_claim_ready_for_payment', array($this, 'notify_payment_ready'), 10, 2);
        add_action('pc_payment_processed', array($this, 'notify_payment_processed'), 10, 2);
        add_action('pc_claim_closed', array($this, 'notify_claim_closed'), 10, 3);

        // Admin notifications
        add_action('pc_new_claim_admin', array($this, 'notify_admin_new_claim'), 10, 2);
        add_action('pc_ready_for_review', array($this, 'notify_admin_review_needed'), 10, 2);

        // AJAX handlers
        add_action('wp_ajax_pc_mark_notification_read', array($this, 'mark_notification_read'));
        add_action('wp_ajax_pc_get_notifications', array($this, 'get_user_notifications'));
    }

    private function init_email_templates() {
        $this->email_templates = array(
            'claim_submitted' => array(
                'subject' => 'Prize Claim #{claim_number} Submitted Successfully',
                'message' => "Hello {user_name},\n\n" .
                            "Your prize claim #{claim_number} has been submitted successfully.\n" .
                            "Amount: \${amount}\n" .
                            "Tournament: {tournament_name}\n\n" .
                            "Next Steps:\n" .
                            "1. Complete W9 Form (manual process)\n" .
                            "2. Set up Direct Deposit\n\n" .
                            "Track your claim status here: {claim_url}"
            ),
            'w9_verified' => array(
                'subject' => 'W9 Form Verified - Prize Claim #{claim_number}',
                'message' => "Hello {user_name},\n\n" .
                            "Your W9 form has been verified.\n" .
                            "You can now proceed with setting up direct deposit for your prize claim."
            ),
            'dd_verified' => array(
                'subject' => 'Direct Deposit Information Verified',
                'message' => "Hello {user_name},\n\n" .
                            "Your direct deposit information has been verified.\n" .
                            "Your claim #{claim_number} is now being processed for payment."
            ),
            'payment_scheduled' => array(
                'subject' => 'Payment Scheduled - Prize Claim #{claim_number}',
                'message' => "Hello {user_name},\n\n" .
                            "Your payment of \${amount} has been scheduled.\n" .
                            "Expected payment date: {payment_date}\n" .
                            "Payment will be sent to your verified bank account."
            ),
            'claim_closed' => array(
                'subject' => 'Prize Claim #{claim_number} Closed',
                'message' => "Hello {user_name},\n\n" .
                            "Your prize claim #{claim_number} has been closed.\n" .
                            "Reason: {closure_reason}\n" .
                            "If you have any questions, please contact support."
            ),
            'admin_new_claim' => array(
                'subject' => 'New Prize Claim #{claim_number} Requires Review',
                'message' => "A new prize claim requires your review:\n\n" .
                            "Claim #: {claim_number}\n" .
                            "User: {user_name}\n" .
                            "Amount: \${amount}\n" .
                            "Tournament: {tournament_name}\n\n" .
                            "Please verify W9 status and review claim details at:\n" .
                            "{admin_url}"
            )
        );
    }

    /**
     * Create Notification
     */
    public function create_notification($user_id, $type, $message, $claim_id = null) {
        global $wpdb;
        
        $data = array(
            'user_id' => $user_id,
            'type' => $type,
            'message' => $message,
            'claim_id' => $claim_id,
            'created_at' => current_time('mysql'),
            'is_read' => 0
        );
        
        $wpdb->insert($wpdb->prefix . 'prize_claim_notifications', $data);
        
        return $wpdb->insert_id;
    }

    /**
     * Send Email
     */
    private function send_email($to, $template, $data) {
        if (!isset($this->email_templates[$template])) {
            return false;
        }

        $subject = $this->parse_template($this->email_templates[$template]['subject'], $data);
        $message = $this->parse_template($this->email_templates[$template]['message'], $data);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>'
        );

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Parse Template
     */
    private function parse_template($template, $data) {
        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        return $template;
    }

    /**
     * Notification Handlers
     */
    public function notify_claim_submitted($claim_id, $user_id) {
        $claim = $this->get_claim_data($claim_id);
        
        // Create in-app notification
        $message = sprintf(
            'Your prize claim #%s for $%s has been submitted successfully.',
            $claim->claim_number,
            number_format($claim->amount, 2)
        );
        $this->create_notification($user_id, 'claim_submitted', $message, $claim_id);

        // Send email
        $user = get_userdata($user_id);
        $email_data = array(
            'user_name' => $user->display_name,
            'claim_number' => $claim->claim_number,
            'amount' => number_format($claim->amount, 2),
            'tournament_name' => $claim->tournament_name,
            'claim_url' => home_url('/prize-claims/')
        );
        $this->send_email($user->user_email, 'claim_submitted', $email_data);

        // Notify admin
        $this->notify_admin_new_claim($claim_id);
    }

    public function notify_w9_status($claim_id, $status) {
        $claim = $this->get_claim_data($claim_id);
        $user = get_userdata($claim->user_id);
        
        $message = 'Your W9 form has been verified.';
        
        $this->create_notification($claim->user_id, 'w9_status', $message, $claim_id);
        
        $email_data = array(
            'user_name' => $user->display_name,
            'claim_number' => $claim->claim_number
        );
        
        $this->send_email($user->user_email, 'w9_verified', $email_data);
    }

    public function notify_dd_status($claim_id, $status, $reason = '') {
        $claim = $this->get_claim_data($claim_id);
        $user = get_userdata($claim->user_id);
        
        $message = $status === 'verified' 
            ? 'Your direct deposit information has been verified.'
            : sprintf('Your direct deposit information needs attention. Reason: %s', $reason);
        
        $this->create_notification($claim->user_id, 'dd_status', $message, $claim_id);
        
        if ($status === 'verified') {
            $email_data = array(
                'user_name' => $user->display_name,
                'claim_number' => $claim->claim_number
            );
            $this->send_email($user->user_email, 'dd_verified', $email_data);
        }
    }

    public function notify_payment_ready($claim_id) {
        $claim = $this->get_claim_data($claim_id);
        $user = get_userdata($claim->user_id);
        
        $message = sprintf(
            'Your claim #%s is ready for payment processing.',
            $claim->claim_number
        );
        
        $this->create_notification($claim->user_id, 'payment_ready', $message, $claim_id);
    }

    public function notify_payment_processed($claim_id, $payment_date) {
        $claim = $this->get_claim_data($claim_id);
        $user = get_userdata($claim->user_id);
        
        $message = sprintf(
            'Payment for claim #%s has been processed. Expected payment date: %s',
            $claim->claim_number,
            date('M j, Y', strtotime($payment_date))
        );
        
        $this->create_notification($claim->user_id, 'payment_processed', $message, $claim_id);
        
        $email_data = array(
            'user_name' => $user->display_name,
            'claim_number' => $claim->claim_number,
            'amount' => number_format($claim->amount, 2),
            'payment_date' => date('M j, Y', strtotime($payment_date))
        );
        
        $this->send_email($user->user_email, 'payment_scheduled', $email_data);
    }

    public function notify_claim_closed($claim_id, $reason, $notes) {
        $claim = $this->get_claim_data($claim_id);
        $user = get_userdata($claim->user_id);
        
        $message = sprintf(
            'Your claim #%s has been closed. Reason: %s',
            $claim->claim_number,
            $reason
        );
        
        $this->create_notification($claim->user_id, 'claim_closed', $message, $claim_id);
        
        $email_data = array(
            'user_name' => $user->display_name,
            'claim_number' => $claim->claim_number,
            'closure_reason' => $reason
        );
        
        $this->send_email($user->user_email, 'claim_closed', $email_data);
    }

    /**
     * Admin Notifications
     */
    public function notify_admin_new_claim($claim_id) {
        $claim = $this->get_claim_data($claim_id);
        $admin_email = get_option('admin_email');
        
        $message = sprintf(
            'New prize claim #%s submitted for $%s by %s',
            $claim->claim_number,
            number_format($claim->amount, 2),
            get_userdata($claim->user_id)->display_name
        );
        
        $this->create_notification(1, 'admin_new_claim', $message, $claim_id);
        
        $email_data = array(
            'claim_number' => $claim->claim_number,
            'amount' => number_format($claim->amount, 2),
            'user_name' => get_userdata($claim->user_id)->display_name,
            'tournament_name' => $claim->tournament_name,
            'admin_url' => admin_url('admin.php?page=prize-claims')
        );
        
        $this->send_email($admin_email, 'admin_new_claim', $email_data);
    }

    public function notify_admin_review_needed($claim_id) {
        $claim = $this->get_claim_data($claim_id);
        $admin_email = get_option('admin_email');
        
        $message = sprintf(
            'Claim #%s requires admin review',
            $claim->claim_number
        );
        
        $this->create_notification(1, 'admin_review', $message, $claim_id);
    }

    /**
     * Helper Functions
     */
    private function get_claim_data($claim_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}prize_claims WHERE id = %d",
            $claim_id
        ));
    }

    public function mark_notification_read() {
        check_ajax_referer('pc-nonce', 'nonce');
        
        $notification_id = intval($_POST['notification_id']);
        
        global $wpdb;
        $updated = $wpdb->update(
            $wpdb->prefix . 'prize_claim_notifications',
            array('is_read' => 1),
            array('id' => $notification_id, 'user_id' => get_current_user_id())
        );
        
        wp_send_json_success($updated);
    }

    public function get_user_notifications() {
        check_ajax_referer('pc-nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 5;
        
        global $wpdb;
        $notifications = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}prize_claim_notifications 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d",
            $user_id,
            $limit
        ));
        
        wp_send_json_success($notifications);
    }
}

// Initialize notifications
new PrizeClaimNotify();