<?php
/**
 * Prize Claim Logging System
 * Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-02-20 01:41:07
 * Current User's Login: EvThatGuy
 */

if (!defined('ABSPATH')) {
    exit;
}

class PrizeClaimLogger {
    private static $instance = null;
    private $log_dir;

    private function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_dir = $upload_dir['basedir'] . '/prize-claim-logs';
        
        // Create log directory if it doesn't exist
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
            // Protect log directory
            file_put_contents($this->log_dir . '/.htaccess', 'deny from all');
            file_put_contents($this->log_dir . '/index.php', '<?php // Silence is golden');
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log shortcode usage
     * 
     * @param string $shortcode The shortcode being used
     * @param int $user_id The user ID
     * @param array $context Additional context data
     */
    public function log_shortcode_usage($shortcode, $user_id, $context = array()) {
        $message = sprintf('Shortcode [%s] accessed', $shortcode);
        $this->log($message, 'shortcode', array_merge([
            'user_id' => $user_id,
            'shortcode' => $shortcode
        ], $context));
    }

    /**
     * Log verification status changes
     * 
     * @param int $user_id The user ID
     * @param string $form_type The form type (w9 or direct_deposit)
     * @param string $status The new status
     * @param array $context Additional context data
     */
    public function log_verification_change($user_id, $form_type, $status, $context = array()) {
        $message = sprintf('Verification status changed for %s form', $form_type);
        $this->log($message, 'verification', array_merge([
            'user_id' => $user_id,
            'form_type' => $form_type,
            'status' => $status
        ], $context));
    }

    /**
     * Log a message with context
     * 
     * @param string $message The log message
     * @param string $type The type of log entry (info, error, success, warning, shortcode, verification)
     * @param array $context Additional context data
     */
    public function log($message, $type = 'info', $context = array()) {
        // Ensure message is a string
        if (!is_string($message)) {
            $message = print_r($message, true);
        }

        // Get user info
        $user_id = get_current_user_id();
        $user_info = get_userdata($user_id);
        $username = $user_info ? $user_info->user_login : 'system';

        // Format log entry
        $log_entry = sprintf(
            "[%s] [%s] [User: %s (%d)] %s\n",
            current_time('mysql'),
            strtoupper($type),
            $username,
            $user_id,
            $message
        );

        // Add context if provided
        if (!empty($context)) {
            $log_entry .= "Context: " . print_r($context, true) . "\n";
        }

        // Add separator
        $log_entry .= str_repeat("-", 80) . "\n";

        // Write to file
        $filename = date('Y-m-d') . '.log';
        file_put_contents(
            $this->log_dir . '/' . $filename,
            $log_entry,
            FILE_APPEND
        );

        // Log critical errors and verification changes to WordPress error log
        if (in_array($type, array('critical', 'error', 'verification'))) {
            error_log("Prize Claim Plugin: " . $message);
        }
    }

    /**
     * Get log contents for a specific date
     * 
     * @param string|null $date Date in Y-m-d format
     * @return string|false
     */
    public function getLog($date = null) {
        if ($date === null) {
            $date = date('Y-m-d');
        }

        $filename = $this->log_dir . '/' . $date . '.log';
        if (file_exists($filename)) {
            return file_get_contents($filename);
        }

        return false;
    }

    /**
     * Clean up old log files
     * 
     * @param int $days Number of days to keep logs
     */
    public function clearLogs($days = 30) {
        $files = glob($this->log_dir . '/*.log');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 60 * 60 * 24 * $days) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Download a log file
     * 
     * @param string|null $date Date in Y-m-d format
     * @return bool
     */
    public function downloadLog($date = null) {
        if ($date === null) {
            $date = date('Y-m-d');
        }

        $filename = $this->log_dir . '/' . $date . '.log';
        if (file_exists($filename)) {
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="prize-claim-' . $date . '.log"');
            readfile($filename);
            exit;
        }

        return false;
    }
}

// Initialize logging system
add_action('init', function() {
    if (!class_exists('PrizeClaimLogger')) {
        return;
    }

    // Setup daily cleanup
    if (!wp_next_scheduled('prize_claim_log_cleanup')) {
        wp_schedule_event(time(), 'daily', 'prize_claim_log_cleanup');
    }
});

// Cleanup old logs
add_action('prize_claim_log_cleanup', function() {
    $logger = PrizeClaimLogger::getInstance();
    $logger->clearLogs(30); // Keep logs for 30 days
});

// Add shortcode logging
add_action('shortcode_added', function($tag) {
    if (in_array($tag, ['prize_claim_form', 'prize_claim_status'])) {
        $logger = PrizeClaimLogger::getInstance();
        $logger->log_shortcode_usage($tag, get_current_user_id());
    }
});

// Example usage in your code:
/*
$logger = PrizeClaimLogger::getInstance();

// Log different types of events
$logger->log('New claim submitted', 'info', [
    'claim_id' => $claim_id,
    'user_id' => $user_id,
    'amount' => $amount
]);

// Log shortcode usage
$logger->log_shortcode_usage('prize_claim_form', $user_id, [
    'page_id' => get_the_ID()
]);

// Log verification status changes
$logger->log_verification_change($user_id, 'w9', 'verified', [
    'form_id' => $form_id,
    'verification_date' => current_time('mysql')
]);
*/