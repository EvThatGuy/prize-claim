<?php
/**
 * Prize Claim Uninstall
 * Current UTC Time: 2025-02-19 21:26:06
 * Current User: EvThatGuy
 * 
 * This file runs when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Only proceed with cleanup if the option is enabled
if (get_option('pc_remove_data_on_uninstall', false)) {
    global $wpdb;

    // Tables to remove
    $tables = array(
        'prize_claims',
        'prize_claim_banking',
        'prize_claim_notifications',
        'prize_claim_activity_log',
        'prize_claim_w9_status',
        'prize_claim_settings'
    );

    // Drop tables
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
    }

    // Remove options
    $options = array(
        'pc_version',
        'pc_db_version',
        'pc_key',
        'pc_remove_data_on_uninstall',
        'pc_settings'
    );

    foreach ($options as $option) {
        delete_option($option);
    }

    // Remove uploaded files
    $upload_dir = wp_upload_dir();
    $prize_claim_dir = $upload_dir['basedir'] . '/prize-claim-logs';

    if (is_dir($prize_claim_dir)) {
        $files = glob($prize_claim_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($prize_claim_dir);
    }

    // Clear any scheduled hooks
    wp_clear_scheduled_hook('prize_claim_log_cleanup');
    wp_clear_scheduled_hook('pc_cleanup');
    wp_clear_scheduled_hook('pc_status_check');

    // Clean up user meta
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'pc_%'");

    // Clean up post meta
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'pc_%'");

    // Remove capabilities from roles
    $roles = array('administrator', 'editor');
    $capabilities = array(
        'manage_prize_claims',
        'view_prize_claims',
        'process_prize_claims',
        'verify_w9_forms',
        'verify_direct_deposits'
    );

    foreach ($roles as $role) {
        $role_obj = get_role($role);
        if ($role_obj) {
            foreach ($capabilities as $cap) {
                $role_obj->remove_cap($cap);
            }
        }
    }

    // Clear any transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pc_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_pc_%'");
}