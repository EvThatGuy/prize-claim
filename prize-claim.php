<?php
/**
 * Plugin Name: Prize Claim
 * Plugin URI: https://github.com/EvThatGuy/prize-claim
 * Description: Prize claim management system with W9 and direct deposit handling
 * Version: 1.0.0
 * Author: EvThatGuy
 * Author URI: https://github.com/EvThatGuy
 * License: GPL v2 or later
 * Text Domain: prize-claim
 * Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-02-20 01:30:39
 * Current User's Login: EvThatGuy
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('PC_VERSION', '1.0.0');
define('PC_DIR', plugin_dir_path(__FILE__));
define('PC_URL', plugin_dir_url(__FILE__));
define('PC_PLUGIN_FILE', __FILE__);
define('PC_PATH', plugin_dir_path(__FILE__));

// Core files
require_once PC_DIR . 'includes/core.php';
require_once PC_DIR . 'includes/database.php';
require_once PC_DIR . 'includes/forms.php';
require_once PC_DIR . 'includes/notify.php';
require_once PC_DIR . 'admin/admin.php';
require_once PC_DIR . 'includes/logging.php';
require_once PC_DIR . 'includes/shortcodes.php';
require_once PC_DIR . 'includes/ajax-handlers.php';
require_once PC_DIR . 'includes/validators.php';

// Initialize plugin
function pc_init() {
    add_action('init', 'pc_load_textdomain');
    
    // Check and update verification statuses if needed
    if (get_option('pc_verification_migration_version', '0') !== PC_VERSION) {
        pc_update_existing_verifications();
    }
    
    $core = new PrizeClaim();
    $core->init();
}

function pc_load_textdomain() {
    load_plugin_textdomain('prize-claim', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

add_action('plugins_loaded', 'pc_init');

// Update existing verification statuses
function pc_update_existing_verifications() {
    global $wpdb;
    
    // Get all unique users with claims
    $users_with_claims = $wpdb->get_col(
        "SELECT DISTINCT user_id FROM {$wpdb->prefix}prize_claims"
    );

    foreach ($users_with_claims as $user_id) {
        // Check existing W9 and direct deposit statuses
        $w9_status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}prize_claim_w9_status 
             WHERE user_id = %d AND status = 'verified'
             ORDER BY verification_date DESC LIMIT 1",
            $user_id
        ));

        $dd_status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}prize_claim_banking 
             WHERE user_id = %d AND status = 'verified'
             ORDER BY verification_date DESC LIMIT 1",
            $user_id
        ));

        // Update claims if verified statuses exist
        if ($w9_status === 'verified' || $dd_status === 'verified') {
            $wpdb->update(
                $wpdb->prefix . 'prize_claims',
                array(
                    'w9_status' => $w9_status ?: 'pending',
                    'direct_deposit_status' => $dd_status ?: 'pending',
                    'last_updated' => current_time('mysql')
                ),
                array('user_id' => $user_id),
                array('%s', '%s', '%s'),
                array('%d')
            );

            // Log the verification status update
            do_action('pc_log', 'verification_migration', 
                sprintf('Updated verification status for user %d: W9=%s, DD=%s', 
                    $user_id, $w9_status ?: 'pending', $dd_status ?: 'pending'
                )
            );
        }
    }

    update_option('pc_verification_migration_version', PC_VERSION);
}

// Enqueue public scripts
function pc_enqueue_public_scripts() {
    wp_enqueue_style(
        'prize-claim-public',
        PC_URL . 'public/css/public.css',
        array(),
        PC_VERSION
    );

    wp_enqueue_script(
        'prize-claim-public',
        PC_URL . 'public/js/public.js',
        array('jquery'),
        PC_VERSION,
        true
    );

    // Get verification statuses for current user if logged in
    $verification_status = array(
        'w9_verified' => false,
        'dd_verified' => false
    );

    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $db = new PrizeClaimDatabase();
        $verification_status['w9_verified'] = $db->get_w9_verification_status($user_id) === 'verified';
        $verification_status['dd_verified'] = $db->get_direct_deposit_status($user_id) === 'verified';
    }

    wp_localize_script('prize-claim-public', 'pcPublic', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pc-public-nonce'),
        'verification_status' => $verification_status,
        'strings' => array(
            'success' => __('Your claim has been submitted successfully.', 'prize-claim'),
            'error' => __('There was an error processing your request.', 'prize-claim'),
            'confirmSubmit' => __('Are you sure you want to submit this claim?', 'prize-claim'),
            'w9Link' => 'https://nextform.app/form/w9Mar2024/1ndeijALNwy8RXzWvQnGkX',
            'w9Required' => __('W9 form is required for claims over ', 'prize-claim') . 
                           get_option('pc_w9_required_amount', 600) . 
                           __('. Please fill out the form at: ', 'prize-claim') . 
                           '<a href="https://nextform.app/form/w9Mar2024/1ndeijALNwy8RXzWvQnGkX" target="_blank">W9 Form</a>'
        )
    ));
}
add_action('wp_enqueue_scripts', 'pc_enqueue_public_scripts');
// Activation
register_activation_hook(__FILE__, 'pc_activate');
function pc_activate() {
    require_once PC_DIR . 'includes/database.php';
    $db = new PrizeClaimDatabase();
    $db->create_tables();
    
    // Set default options
    add_option('pc_version', PC_VERSION);
    add_option('pc_key', base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
    add_option('pc_remove_data_on_uninstall', false);
    add_option('pc_w9_required_amount', 600);
    add_option('pc_minimum_payout_amount', 10);
    add_option('pc_enable_notifications', true);
    add_option('pc_notification_email', get_option('admin_email'));
    add_option('pc_verification_migration_version', '0');  // Added for verification tracking
    
    // Create required directories
    $upload_dir = wp_upload_dir();
    $prize_claim_dir = $upload_dir['basedir'] . '/prize-claims';
    if (!file_exists($prize_claim_dir)) {
        wp_mkdir_p($prize_claim_dir);
        wp_mkdir_p($prize_claim_dir . '/direct-deposit');
    }
    
    // Create .htaccess to protect uploaded files
    $htaccess_content = "Order deny,allow\nDeny from all";
    @file_put_contents($prize_claim_dir . '/.htaccess', $htaccess_content);
    
    // Run verification migration for existing users
    pc_update_existing_verifications();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation
register_deactivation_hook(__FILE__, 'pc_deactivate');
function pc_deactivate() {
    wp_clear_scheduled_hook('pc_cleanup');
    wp_clear_scheduled_hook('pc_status_check');
    wp_clear_scheduled_hook('pc_notifications');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Register uninstall hook
register_uninstall_hook(__FILE__, 'pc_uninstall');
function pc_uninstall() {
    if (get_option('pc_remove_data_on_uninstall', false)) {
        global $wpdb;
        
        // Clean up database tables and options
        $database = new PrizeClaimDatabase();
        $database->uninstall();
        
        // Remove uploaded files
        $upload_dir = wp_upload_dir();
        $prize_claim_dir = $upload_dir['basedir'] . '/prize-claims';
        if (file_exists($prize_claim_dir)) {
            pc_recursive_rmdir($prize_claim_dir);
        }
        
        // Remove all plugin options
        $options = array(
            'pc_version',
            'pc_key',
            'pc_remove_data_on_uninstall',
            'pc_db_version',
            'pc_w9_required_amount',
            'pc_minimum_payout_amount',
            'pc_enable_notifications',
            'pc_notification_email',
            'pc_verification_migration_version'  // Added for verification tracking
        );
        
        foreach ($options as $option) {
            delete_option($option);
        }
    }
}

// Helper function to recursively remove directories
function pc_recursive_rmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . "/" . $object)) {
                    pc_recursive_rmdir($dir . "/" . $object);
                } else {
                    unlink($dir . "/" . $object);
                }
            }
        }
        rmdir($dir);
    }
}

// Add admin notice for verification migration status
function pc_admin_migration_notice() {
    if (current_user_can('manage_options')) {
        $migration_version = get_option('pc_verification_migration_version', '0');
        if ($migration_version !== PC_VERSION) {
            ?>
            <div class="notice notice-info is-dismissible">
                <p><?php _e('Prize Claim plugin is updating verification statuses for existing users. This may take a moment.', 'prize-claim'); ?></p>
            </div>
            <?php
        }
    }
}
add_action('admin_notices', 'pc_admin_migration_notice');

// Security - prevent direct file access
if (!defined('WPINC')) {
    die;
}
