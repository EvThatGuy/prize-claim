<?php
/**
 * Prize Claim Forms
 * Current UTC Time: 2025-02-19 19:27:32
 * Current User: EvThatGuy
 */

if (!defined('ABSPATH')) {
    exit;
}

class PrizeClaimForms {
    private $prize_form_id = 51;
    private $deposit_form_id = 91;

    public function __construct() {
        // Form creation hooks
        add_action('gform_loaded', array($this, 'create_forms'));
        
        // Form submission hooks
        add_action('gform_after_submission_' . $this->prize_form_id, array($this, 'handle_prize_claim_submission'), 10, 2);
        add_action('gform_after_submission_' . $this->deposit_form_id, array($this, 'handle_direct_deposit_submission'), 10, 2);
        
        // Encryption hooks
        add_filter('gform_entry_post_save', array($this, 'encrypt_sensitive_fields'), 10, 2);
        
        // Validation hooks
        add_filter('gform_validation_' . $this->deposit_form_id, array($this, 'validate_bank_info'));
    }

    public function create_forms() {
        if (!class_exists('GFForms')) {
            return;
        }

        $this->create_prize_claim_form();
        $this->create_direct_deposit_form();
    }

    private function create_prize_claim_form() {
        $form_id = $this->prize_form_id;
        
        if ($this->form_exists($form_id)) {
            return;
        }

        $form = array(
            'id' => $form_id,
            'title' => 'Prize Claim Form',
            'description' => 'Submit your prize claim information',
            'fields' => array(
                array(
                    'type' => 'text',
                    'id' => 1,
                    'label' => 'First Name',
                    'isRequired' => true,
                    'placeholder' => 'Enter your first name'
                ),
                array(
                    'type' => 'text',
                    'id' => 2,
                    'label' => 'Last Name',
                    'isRequired' => true,
                    'placeholder' => 'Enter your last name'
                ),
                array(
                    'type' => 'email',
                    'id' => 3,
                    'label' => 'Email',
                    'isRequired' => true,
                    'placeholder' => 'Enter your email address'
                ),
                array(
                    'type' => 'text',
                    'id' => 4,
                    'label' => 'Tournament Name',
                    'isRequired' => true,
                    'placeholder' => 'Enter tournament name'
                ),
                array(
                    'type' => 'text',
                    'id' => 5,
                    'label' => 'Username/Gamertag',
                    'isRequired' => true,
                    'placeholder' => 'Enter your username/gamertag'
                ),
                array(
                    'type' => 'select',
                    'id' => 6,
                    'label' => 'Prize Type',
                    'isRequired' => true,
                    'choices' => array(
                        array('text' => 'Tournament Prize', 'value' => 'tournament'),
                        array('text' => 'Challenge Prize', 'value' => 'challenge')
                    )
                ),
                array(
                    'type' => 'number',
                    'id' => 7,
                    'label' => 'Prize Amount',
                    'isRequired' => true,
                    'placeholder' => 'Enter prize amount',
                    'numberFormat' => 'currency'
                ),
                array(
                    'type' => 'html',
                    'id' => 8,
                    'label' => 'W9 Information',
                    'content' => $this->get_w9_notice_content()
                )
            )
        );

        GFAPI::add_form($form);
    }

    private function create_direct_deposit_form() {
        $form_id = $this->deposit_form_id;
        
        if ($this->form_exists($form_id)) {
            return;
        }

        $form = array(
            'id' => $form_id,
            'title' => 'Direct Deposit Information',
            'description' => 'Enter your banking information for prize payment',
            'fields' => array(
                array(
                    'type' => 'text',
                    'id' => 1,
                    'label' => 'Bank Name',
                    'isRequired' => true,
                    'placeholder' => 'Enter your bank name'
                ),
                array(
                    'type' => 'text',
                    'id' => 2,
                    'label' => 'Account Holder Name',
                    'isRequired' => true,
                    'placeholder' => 'Enter the name on the account'
                ),
                array(
                    'type' => 'text',
                    'id' => 3,
                    'label' => 'Routing Number',
                    'isRequired' => true,
                    'placeholder' => 'Enter 9-digit routing number',
                    'maxLength' => 9,
                    'validation' => array(
                        'pattern' => '^[0-9]{9}$',
                        'message' => 'Please enter a valid 9-digit routing number'
                    )
                ),
                array(
                    'type' => 'text',
                    'id' => 4,
                    'label' => 'Account Number',
                    'isRequired' => true,
                    'placeholder' => 'Enter account number',
                    'validation' => array(
                        'pattern' => '^[0-9]{4,17}$',
                        'message' => 'Please enter a valid account number (4-17 digits)'
                    )
                ),
                array(
                    'type' => 'select',
                    'id' => 5,
                    'label' => 'Account Type',
                    'isRequired' => true,
                    'choices' => array(
                        array('text' => 'Checking', 'value' => 'checking'),
                        array('text' => 'Savings', 'value' => 'savings')
                    )
                ),
                array(
                    'type' => 'consent',
                    'id' => 6,
                    'label' => 'Terms & Conditions',
                    'isRequired' => true,
                    'description' => 'I confirm that the banking information provided is accurate and authorize direct deposit payments to this account.'
                )
            )
        );

        GFAPI::add_form($form);
    }

    private function form_exists($form_id) {
        return GFAPI::form_id_exists($form_id);
    }

    public function encrypt_sensitive_fields($entry, $form) {
        if ($form['id'] == $this->deposit_form_id) {
            // Get encryption key
            $key = get_option('pc_key');
            if (!$key) {
                return $entry;
            }

            // Encrypt routing and account numbers
            $entry[3] = $this->encrypt_field($entry[3], $key);
            $entry[4] = $this->encrypt_field($entry[4], $key);
        }
        return $entry;
    }

    private function encrypt_field($value, $key) {
        if (empty($value)) return '';
        
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($value, $nonce, base64_decode($key));
        
        return base64_encode($nonce . $cipher);
    }

    public function handle_prize_claim_submission($entry, $form) {
        global $wpdb;
        
        // Create claim record
        $claim_data = array(
            'user_id' => get_current_user_id(),
            'tournament_name' => rgar($entry, 4),
            'amount' => floatval(rgar($entry, 7)),
            'status' => 'pending',
            'w9_status' => 'pending',
            'submission_date' => current_time('mysql'),
            'last_updated' => current_time('mysql'),
            'claim_number' => $this->generate_claim_number()
        );
        
        $wpdb->insert($wpdb->prefix . 'prize_claims', $claim_data);
        $claim_id = $wpdb->insert_id;
        
        if ($claim_id) {
            do_action('pc_claim_submitted', $claim_id, $claim_data['user_id']);
        }
    }

    public function handle_direct_deposit_submission($entry, $form) {
        global $wpdb;
        
        // Save banking info
        $banking_data = array(
            'user_id' => get_current_user_id(),
            'bank_name' => rgar($entry, 1),
            'account_holder' => rgar($entry, 2),
            'routing_number_hash' => rgar($entry, 3),
            'account_number_hash' => rgar($entry, 4),
            'account_type' => rgar($entry, 5),
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $wpdb->insert($wpdb->prefix . 'prize_claim_banking', $banking_data);
    }

    public function validate_bank_info($validation_result) {
        $form = $validation_result['form'];
        
        // Add additional bank validation logic here
        
        return $validation_result;
    }

    private function get_w9_notice_content() {
        return '<div class="w9-notice">
            <p><strong>W9 Information:</strong></p>
            <p>For prizes over $600, a W9 form must be completed and verified before payment can be processed.</p>
            <p>Our admin team will contact you about W9 verification if needed.</p>
        </div>';
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
}

// Initialize forms
function initialize_prize_claim_forms() {
    new PrizeClaimForms();
}
add_action('init', 'initialize_prize_claim_forms');