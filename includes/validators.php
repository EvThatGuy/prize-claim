<?php
/**
 * Prize Claim Validators
 * Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-02-20 00:10:23
 * Current User's Login: EvThatGuy
 */

if (!defined('ABSPATH')) {
    exit;
}

class PrizeClaimValidators {
    /**
     * Validate claim submission data
     * 
     * @param array $data The claim submission data
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validate_claim_submission($data) {
        $errors = array();
        
        // Event name validation
        if (empty($data['event_name'])) {
            $errors['event_name'] = __('Event name is required', 'prize-claim');
        } elseif (strlen($data['event_name']) > 255) {
            $errors['event_name'] = __('Event name is too long (maximum 255 characters)', 'prize-claim');
        }

        // Amount validation
        if (!isset($data['amount'])) {
            $errors['amount'] = __('Prize amount is required', 'prize-claim');
        } else {
            $amount = floatval($data['amount']);
            $min_amount = floatval(get_option('pc_minimum_payout_amount', 10));
            
            if ($amount < $min_amount) {
                $errors['amount'] = sprintf(
                    __('Prize amount must be at least $%s', 'prize-claim'),
                    number_format($min_amount, 2)
                );
            } elseif ($amount > 1000000) {
                $errors['amount'] = __('Prize amount exceeds maximum limit', 'prize-claim');
            }
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    /**
     * Validate payment processing data
     * 
     * @param array $data The payment data
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validate_payment_processing($data) {
        $errors = array();

        // Payment date validation
        if (empty($data['payment_date'])) {
            $errors['payment_date'] = __('Payment date is required', 'prize-claim');
        } else {
            $payment_date = strtotime($data['payment_date']);
            $current_date = strtotime(current_time('mysql'));
            
            if ($payment_date === false) {
                $errors['payment_date'] = __('Invalid payment date format', 'prize-claim');
            } elseif ($payment_date < $current_date) {
                $errors['payment_date'] = __('Payment date cannot be in the past', 'prize-claim');
            } elseif ($payment_date > strtotime('+1 year', $current_date)) {
                $errors['payment_date'] = __('Payment date cannot be more than 1 year in the future', 'prize-claim');
            }
        }

        // Payment notes validation
        if (!empty($data['payment_notes']) && strlen($data['payment_notes']) > 1000) {
            $errors['payment_notes'] = __('Payment notes are too long (maximum 1000 characters)', 'prize-claim');
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }

    /**
     * Validate claim closure data
     * 
     * @param array $data The closure data
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validate_claim_closure($data) {
        $errors = array();

        // Closure reason validation
        if (empty($data['closure_reason'])) {
            $errors['closure_reason'] = __('Closure reason is required', 'prize-claim');
        } else {
            $valid_reasons = array('completed', 'cancelled', 'duplicate', 'invalid', 'other');
            if (!in_array($data['closure_reason'], $valid_reasons)) {
                $errors['closure_reason'] = __('Invalid closure reason', 'prize-claim');
            }
        }

        // Closure notes validation
        if ($data['closure_reason'] === 'other' && empty($data['closure_notes'])) {
            $errors['closure_notes'] = __('Notes are required when selecting "Other" as closure reason', 'prize-claim');
        }
        if (!empty($data['closure_notes']) && strlen($data['closure_notes']) > 1000) {
            $errors['closure_notes'] = __('Closure notes are too long (maximum 1000 characters)', 'prize-claim');
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }

    /**
     * Validate W9 verification data
     * 
     * @param array $data The W9 verification data
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validate_w9_verification($data) {
        $errors = array();

        // User ID validation
        if (empty($data['user_id']) || !is_numeric($data['user_id'])) {
            $errors['user_id'] = __('Invalid user ID', 'prize-claim');
        } else {
            $user = get_user_by('ID', $data['user_id']);
            if (!$user) {
                $errors['user_id'] = __('User not found', 'prize-claim');
            }
        }
        // Verification notes validation
        if (empty($data['verification_notes'])) {
            $errors['verification_notes'] = __('Verification notes are required', 'prize-claim');
        } elseif (strlen($data['verification_notes']) > 1000) {
            $errors['verification_notes'] = __('Verification notes are too long (maximum 1000 characters)', 'prize-claim');
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }

    /**
     * Sanitize and format amount
     * 
     * @param mixed $amount The amount to sanitize
     * @return float
     */
    public static function sanitize_amount($amount) {
        // Remove any currency symbols and commas
        $amount = preg_replace('/[^0-9.]/', '', $amount);
        return round(floatval($amount), 2);
    }

    /**
     * Validate claim status transition
     * 
     * @param string $current_status Current claim status
     * @param string $new_status Proposed new status
     * @return bool
     */
    public static function is_valid_status_transition($current_status, $new_status) {
        $valid_transitions = array(
            'pending' => array('processing', 'closed'),
            'processing' => array('completed', 'closed'),
            'completed' => array('closed'),
            'closed' => array() // Cannot transition from closed
        );

        return isset($valid_transitions[$current_status]) && 
               in_array($new_status, $valid_transitions[$current_status]);
    }

    /**
     * Get list of valid claim statuses
     * 
     * @return array
     */
    public static function get_valid_statuses() {
        return array(
            'pending' => __('Pending', 'prize-claim'),
            'processing' => __('Processing', 'prize-claim'),
            'completed' => __('Completed', 'prize-claim'),
            'closed' => __('Closed', 'prize-claim')
        );
    }

    /**
     * Get list of valid closure reasons
     * 
     * @return array
     */
    public static function get_valid_closure_reasons() {
        return array(
            'completed' => __('Payment Completed', 'prize-claim'),
            'cancelled' => __('Cancelled by User', 'prize-claim'),
            'duplicate' => __('Duplicate Claim', 'prize-claim'),
            'invalid' => __('Invalid Claim', 'prize-claim'),
            'other' => __('Other', 'prize-claim')
        );
    }
}
        