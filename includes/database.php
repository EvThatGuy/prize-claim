<?php
/**
 * Prize Claim Database Tables
 * Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-02-20 01:52:15
 * Current User's Login: EvThatGuy
 */

if (!defined('ABSPATH')) {
    exit;
}

class PrizeClaimDatabase {
    private $version = '1.0.3';

    public function __construct() {
        add_action('activate_prize-claim/prize-claim.php', array($this, 'create_tables'));
        add_action('plugins_loaded', array($this, 'check_version'));
    }

    public function check_version() {
        $current_version = get_option('pc_db_version', '0.0.0');
        if (version_compare($current_version, $this->version, '<')) {
            $this->create_tables();
            $this->update_tables($current_version);
            update_option('pc_db_version', $this->version);
        }
    }

    private function update_tables($old_version) {
        global $wpdb;
        
        // Check existing columns first
        $existing_columns = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}prize_claims");
        
        if (version_compare($old_version, '1.0.1', '<')) {
            // Add dd_document_path only if it doesn't exist
            if (!in_array('dd_document_path', $existing_columns)) {
                $wpdb->query("ALTER TABLE {$wpdb->prefix}prize_claims 
                             ADD COLUMN dd_document_path VARCHAR(255) DEFAULT NULL 
                             AFTER direct_deposit_status");
            }
        }

        if (version_compare($old_version, '1.0.2', '<')) {
            // Add new columns only if they don't exist
            if (!in_array('bulk_process_id', $existing_columns)) {
                $wpdb->query("ALTER TABLE {$wpdb->prefix}prize_claims 
                             ADD COLUMN bulk_process_id VARCHAR(50) DEFAULT NULL 
                             AFTER payment_notes");
            }
            if (!in_array('last_exported', $existing_columns)) {
                $wpdb->query("ALTER TABLE {$wpdb->prefix}prize_claims 
                             ADD COLUMN last_exported DATETIME DEFAULT NULL 
                             AFTER last_updated");
            }
        }

        if (version_compare($old_version, '1.0.3', '<')) {
            // Only try to drop w9_document_path if it exists
            if (in_array('w9_document_path', $existing_columns)) {
                $wpdb->query("ALTER TABLE {$wpdb->prefix}prize_claims 
                             DROP COLUMN w9_document_path");
            }
        }
    }
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Log any database errors
        $wpdb->show_errors();

        // Prize Claims Table
        $claims_table = $wpdb->prefix . 'prize_claims';
        $sql_claims = "CREATE TABLE IF NOT EXISTS $claims_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            claim_number varchar(50) NOT NULL,
            tournament_name varchar(255) NOT NULL,
            amount decimal(10,2) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            w9_status varchar(50) NOT NULL DEFAULT 'pending',
            direct_deposit_status varchar(50) NOT NULL DEFAULT 'pending',
            dd_document_path varchar(255) DEFAULT NULL,
            payment_date datetime DEFAULT NULL,
            payment_amount decimal(10,2) DEFAULT NULL,
            payment_notes text,
            bulk_process_id varchar(50) DEFAULT NULL,
            submission_date datetime NOT NULL,
            last_updated datetime NOT NULL,
            last_exported datetime DEFAULT NULL,
            notes text,
            closure_reason varchar(255) DEFAULT NULL,
            closure_notes text,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY claim_number (claim_number),
            KEY status (status),
            KEY bulk_process_id (bulk_process_id)
        ) $charset_collate;";

        // Direct Deposit Information Table (Encrypted)
        $deposit_table = $wpdb->prefix . 'prize_claim_banking';
        $sql_deposit = "CREATE TABLE IF NOT EXISTS $deposit_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            claim_id bigint(20) NOT NULL,
            bank_name varchar(255) NOT NULL,
            account_holder varchar(255) NOT NULL,
            routing_number_hash varchar(255) NOT NULL,
            account_number_hash varchar(255) NOT NULL,
            routing_last4 varchar(4) DEFAULT NULL,
            account_last4 varchar(4) DEFAULT NULL,
            account_type varchar(50) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            verification_date datetime DEFAULT NULL,
            verified_by bigint(20) DEFAULT NULL,
            verification_notes text,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY claim_id (claim_id),
            KEY status (status)
        ) $charset_collate;";

        // Notifications Table
        $notifications_table = $wpdb->prefix . 'prize_claim_notifications';
        $sql_notifications = "CREATE TABLE IF NOT EXISTS $notifications_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            claim_id bigint(20) DEFAULT NULL,
            type varchar(50) NOT NULL,
            message text NOT NULL,
            is_read tinyint(1) NOT NULL DEFAULT '0',
            created_at datetime NOT NULL,
            read_at datetime DEFAULT NULL,
            notification_hash varchar(32) NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY claim_id (claim_id),
            KEY type (type),
            KEY is_read (is_read),
            UNIQUE KEY notification_hash (notification_hash)
        ) $charset_collate;";

        // Activity Log Table
        $activity_table = $wpdb->prefix . 'prize_claim_activity_log';
        $sql_activity = "CREATE TABLE IF NOT EXISTS $activity_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            claim_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            action varchar(50) NOT NULL,
            description text NOT NULL,
            created_at datetime NOT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            bulk_action_id varchar(50) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY claim_id (claim_id),
            KEY user_id (user_id),
            KEY action (action),
            KEY bulk_action_id (bulk_action_id)
        ) $charset_collate;";
        // W9 Status Table (Enhanced for External Form)
        $w9_table = $wpdb->prefix . 'prize_claim_w9_status';
        $sql_w9 = "CREATE TABLE IF NOT EXISTS $w9_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            verified_by bigint(20) NOT NULL,
            verification_date datetime NOT NULL,
            external_form_id varchar(255) DEFAULT NULL,
            submission_date datetime DEFAULT NULL,
            notes text,
            status varchar(50) NOT NULL DEFAULT 'pending',
            expiration_date datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY expiration_date (expiration_date)
        ) $charset_collate;";

        // Settings Table
        $settings_table = $wpdb->prefix . 'prize_claim_settings';
        $sql_settings = "CREATE TABLE IF NOT EXISTS $settings_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            setting_name varchar(255) NOT NULL,
            setting_value text NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            autoload enum('yes','no') NOT NULL DEFAULT 'yes',
            PRIMARY KEY (id),
            UNIQUE KEY setting_name (setting_name),
            KEY autoload (autoload)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create tables and log any errors
        $tables = array(
            'claims' => $sql_claims,
            'deposit' => $sql_deposit,
            'notifications' => $sql_notifications,
            'activity' => $sql_activity,
            'w9' => $sql_w9,
            'settings' => $sql_settings
        );

        foreach ($tables as $name => $sql) {
            $result = dbDelta($sql);
            if (is_wp_error($result)) {
                error_log("Prize Claim DB Error creating $name table: " . $result->get_error_message());
            }
        }

        // Add default settings
        $this->add_default_settings();
    }
    private function add_default_settings() {
        global $wpdb;
        $settings_table = $wpdb->prefix . 'prize_claim_settings';
        $current_time = current_time('mysql');

        $default_settings = array(
            array(
                'setting_name' => 'minimum_payout_amount',
                'setting_value' => '10.00',
                'created_at' => $current_time,
                'updated_at' => $current_time,
                'autoload' => 'yes'
            ),
            array(
                'setting_name' => 'w9_required_amount',
                'setting_value' => '600.00',
                'created_at' => $current_time,
                'updated_at' => $current_time,
                'autoload' => 'yes'
            ),
            array(
                'setting_name' => 'w9_form_url',
                'setting_value' => 'https://nextform.app/form/w9Mar2024/1ndeijALNwy8RXzWvQnGkX',
                'created_at' => $current_time,
                'updated_at' => $current_time,
                'autoload' => 'yes'
            ),
            array(
                'setting_name' => 'direct_deposit_form_url',
                'setting_value' => 'https://nextform.app/form/dd2024/default',
                'created_at' => $current_time,
                'updated_at' => $current_time,
                'autoload' => 'yes'
            ),
            array(
                'setting_name' => 'notification_email',
                'setting_value' => get_option('admin_email'),
                'created_at' => $current_time,
                'updated_at' => $current_time,
                'autoload' => 'yes'
            ),
            array(
                'setting_name' => 'bulk_process_batch_size',
                'setting_value' => '50',
                'created_at' => $current_time,
                'updated_at' => $current_time,
                'autoload' => 'yes'
            ),
            array(
                'setting_name' => 'w9_expiration_months',
                'setting_value' => '12',
                'created_at' => $current_time,
                'updated_at' => $current_time,
                'autoload' => 'yes'
            )
        );

        // Use replace instead of insert to handle existing settings
        foreach ($default_settings as $setting) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $settings_table WHERE setting_name = %s",
                $setting['setting_name']
            ));

            if (!$exists) {
                $wpdb->insert($settings_table, $setting);
            }
        }
    }
    /**
     * Get user's W9 verification status
     * 
     * @param int $user_id
     * @return string Status ('pending', 'verified', 'expired', or null)
     */
    public function get_w9_verification_status($user_id) {
        global $wpdb;
        
        // Check for valid W9 that hasn't expired
        $status = $wpdb->get_row($wpdb->prepare(
            "SELECT status, expiration_date 
             FROM {$wpdb->prefix}prize_claim_w9_status 
             WHERE user_id = %d 
             AND status = 'verified'
             ORDER BY verification_date DESC 
             LIMIT 1",
            $user_id
        ));

        if (!$status) {
            return 'pending';
        }

        // If there's an expiration date, check if it's still valid
        if ($status->expiration_date && strtotime($status->expiration_date) < time()) {
            return 'expired';
        }

        return $status->status;
    }

    /**
     * Get user's direct deposit verification status
     * 
     * @param int $user_id
     * @return string Status ('pending', 'verified', or null)
     */
    public function get_direct_deposit_status($user_id) {
        global $wpdb;
        
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status 
             FROM {$wpdb->prefix}prize_claim_banking 
             WHERE user_id = %d 
             AND status = 'verified'
             ORDER BY verification_date DESC 
             LIMIT 1",
            $user_id
        ));

        return $status ?: 'pending';
    }

    /**
     * Get user's claims with verification status
     * 
     * @param int $user_id
     * @return array Claims with verification status
     */
    public function get_user_claims($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*,
                    w.status as w9_current_status,
                    b.status as dd_current_status
             FROM {$wpdb->prefix}prize_claims c
             LEFT JOIN (
                 SELECT user_id, status 
                 FROM {$wpdb->prefix}prize_claim_w9_status 
                 WHERE status = 'verified'
                 GROUP BY user_id
             ) w ON c.user_id = w.user_id
             LEFT JOIN (
                 SELECT user_id, status
                 FROM {$wpdb->prefix}prize_claim_banking
                 WHERE status = 'verified'
                 GROUP BY user_id
             ) b ON c.user_id = b.user_id
             WHERE c.user_id = %d
             ORDER BY c.submission_date DESC",
            $user_id
        ));
    }
    /**
     * Get claim details with verification status
     * 
     * @param int $claim_id
     * @return object Claim with verification status
     */
    public function get_claim($claim_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT c.*,
                    w.status as w9_current_status,
                    b.status as dd_current_status
             FROM {$wpdb->prefix}prize_claims c
             LEFT JOIN (
                 SELECT user_id, status 
                 FROM {$wpdb->prefix}prize_claim_w9_status 
                 WHERE status = 'verified'
                 GROUP BY user_id
             ) w ON c.user_id = w.user_id
             LEFT JOIN (
                 SELECT user_id, status
                 FROM {$wpdb->prefix}prize_claim_banking
                 WHERE status = 'verified'
                 GROUP BY user_id
             ) b ON c.user_id = b.user_id
             WHERE c.id = %d",
            $claim_id
        ));
    }

    /**
     * Create new claim with verification status
     * 
     * @param array $claim_data
     * @return int|false The claim ID on success, false on failure
     */
    public function create_claim($claim_data) {
        global $wpdb;
        
        // Ensure required fields
        $required_fields = array('user_id', 'tournament_name', 'amount', 'submission_date', 'claim_number');
        foreach ($required_fields as $field) {
            if (!isset($claim_data[$field])) {
                error_log("Prize Claim Error: Missing required field '$field' in create_claim");
                return false;
            }
        }

        // Set default values if not provided
        $claim_data = array_merge(
            array(
                'status' => 'pending',
                'w9_status' => 'pending',
                'direct_deposit_status' => 'pending',
                'last_updated' => current_time('mysql')
            ),
            $claim_data
        );

        // Insert claim
        $result = $wpdb->insert(
            $wpdb->prefix . 'prize_claims',
            $claim_data,
            array(
                '%d', // user_id
                '%s', // tournament_name
                '%f', // amount
                '%s', // submission_date
                '%s', // claim_number
                '%s', // status
                '%s', // w9_status
                '%s'  // direct_deposit_status
            )
        );

        if ($result === false) {
            error_log("Prize Claim Error: Failed to create claim - " . $wpdb->last_error);
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Log activity for a claim
     * 
     * @param int $claim_id
     * @param int $user_id
     * @param string $action
     * @param string $description
     * @param string $bulk_action_id
     * @return int|false The activity log ID on success, false on failure
     */
    public function log_activity($claim_id, $user_id, $action, $description, $bulk_action_id = null) {
        global $wpdb;
        
        $data = array(
            'claim_id' => $claim_id,
            'user_id' => $user_id,
            'action' => $action,
            'description' => $description,
            'created_at' => current_time('mysql'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'bulk_action_id' => $bulk_action_id
        );

        $result = $wpdb->insert(
            $wpdb->prefix . 'prize_claim_activity_log',
            $data,
            array(
                '%d', // claim_id
                '%d', // user_id
                '%s', // action
                '%s', // description
                '%s', // created_at
                '%s', // ip_address
                '%s', // user_agent
                '%s'  // bulk_action_id
            )
        );

        if ($result === false) {
            error_log("Prize Claim Error: Failed to log activity - " . $wpdb->last_error);
            return false;
        }

        return $wpdb->insert_id;
    }
    /**
     * Get setting value
     * 
     * @param string $setting_name
     * @param mixed $default Default value if setting not found
     * @return mixed Setting value or default
     */
    public function get_setting($setting_name, $default = null) {
        global $wpdb;
        
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value 
             FROM {$wpdb->prefix}prize_claim_settings 
             WHERE setting_name = %s",
            $setting_name
        ));

        if ($wpdb->last_error) {
            error_log("Prize Claim Error: Failed to get setting '$setting_name' - " . $wpdb->last_error);
        }

        return $value !== null ? $value : $default;
    }

    /**
     * Update setting value
     * 
     * @param string $setting_name
     * @param mixed $setting_value
     * @param string $autoload Optional. Default 'yes'
     * @return bool True on success, false on failure
     */
    public function update_setting($setting_name, $setting_value, $autoload = 'yes') {
        global $wpdb;
        
        $data = array(
            'setting_value' => $setting_value,
            'updated_at' => current_time('mysql'),
            'autoload' => $autoload
        );

        $where = array('setting_name' => $setting_name);

        $result = $wpdb->update(
            $wpdb->prefix . 'prize_claim_settings',
            $data,
            $where,
            array('%s', '%s', '%s'),
            array('%s')
        );

        if ($result === false) {
            error_log("Prize Claim Error: Failed to update setting '$setting_name' - " . $wpdb->last_error);
            return false;
        }

        return true;
    }

    public function uninstall() {
        if (get_option('pc_remove_data_on_uninstall', false)) {
            global $wpdb;
            
            // Define tables to remove
            $tables = array(
                'prize_claims',
                'prize_claim_banking',
                'prize_claim_notifications',
                'prize_claim_activity_log',
                'prize_claim_w9_status',
                'prize_claim_settings'
            );

            // Drop each table and log any errors
            foreach ($tables as $table) {
                $full_table_name = $wpdb->prefix . $table;
                
                // Check if table exists before attempting to drop
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name;
                
                if ($table_exists) {
                    $result = $wpdb->query("DROP TABLE IF EXISTS $full_table_name");
                    if ($result === false) {
                        error_log("Prize Claim Error: Failed to drop table '$table' - " . $wpdb->last_error);
                    }
                }
            }

            // Clean up options
            delete_option('pc_db_version');
            delete_option('pc_remove_data_on_uninstall');
        }
    }
}

// Initialize database
new PrizeClaimDatabase();