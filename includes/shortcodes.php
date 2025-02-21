<?php
/**
 * Prize Claim Shortcodes
 * Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-02-20 01:34:48
 * Current User's Login: EvThatGuy
 */

if (!defined('ABSPATH')) {
    exit;
}

class PrizeClaimShortcodes {
    private $db;

    public function __construct() {
        $this->db = new PrizeClaimDatabase();
        $this->register_shortcodes();
    }

    public function register_shortcodes() {
        add_shortcode('prize_claim_form', array($this, 'render_claim_form'));
        add_shortcode('prize_claim_status', array($this, 'render_claim_status'));
    }

    /**
     * Render the prize claim form
     */
    public function render_claim_form($atts) {
        if (!is_user_logged_in()) {
            return '<div class="pc-alert pc-alert-warning">' . 
                   __('Please log in to submit a prize claim.', 'prize-claim') . 
                   '</div>';
        }

        $user_id = get_current_user_id();
        
        // Check verification statuses
        $w9_status = $this->db->get_w9_verification_status($user_id);
        $dd_status = $this->db->get_direct_deposit_status($user_id);

        ob_start();
        ?>
        <div class="pc-claim-form-wrapper">
            <?php if ($w9_status !== 'verified' || $dd_status !== 'verified'): ?>
                <div class="pc-verification-status">
                    <?php if ($w9_status !== 'verified'): ?>
                        <div class="pc-alert pc-alert-info">
                            <?php _e('W9 form verification required. ', 'prize-claim'); ?>
                            <a href="<?php echo esc_url($this->get_w9_form_url()); ?>" target="_blank">
                                <?php _e('Submit W9 Form', 'prize-claim'); ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($dd_status !== 'verified'): ?>
                        <div class="pc-alert pc-alert-info">
                            <?php _e('Direct deposit verification required. ', 'prize-claim'); ?>
                            <a href="<?php echo esc_url($this->get_direct_deposit_form_url()); ?>" target="_blank">
                                <?php _e('Submit Direct Deposit Form', 'prize-claim'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form id="pc-claim-form" class="pc-form">
                <?php wp_nonce_field('pc-claim-submission', 'pc_nonce'); ?>
                
                <div class="pc-form-row">
                    <label for="tournament_name">
                        <?php _e('Tournament Name', 'prize-claim'); ?> <span class="required">*</span>
                    </label>
                    <input type="text" id="tournament_name" name="tournament_name" required>
                </div>

                <div class="pc-form-row">
                    <label for="amount">
                        <?php _e('Prize Amount', 'prize-claim'); ?> <span class="required">*</span>
                    </label>
                    <input type="number" id="amount" name="amount" step="0.01" min="0" required>
                </div>

                <div class="pc-form-row">
                    <button type="submit" class="pc-submit-btn" <?php echo ($w9_status !== 'verified' || $dd_status !== 'verified') ? 'disabled' : ''; ?>>
                        <?php _e('Submit Claim', 'prize-claim'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the claim status display
     */
    public function render_claim_status($atts) {
        if (!is_user_logged_in()) {
            return '<div class="pc-alert pc-alert-warning">' . 
                   __('Please log in to view your prize claims.', 'prize-claim') . 
                   '</div>';
        }

        $user_id = get_current_user_id();
        $claims = $this->db->get_user_claims($user_id);

        ob_start();
        ?>
        <div class="pc-claims-status-wrapper">
            <?php if (empty($claims)): ?>
                <div class="pc-alert pc-alert-info">
                    <?php _e('No prize claims found.', 'prize-claim'); ?>
                </div>
            <?php else: ?>
                <table class="pc-claims-table">
                    <thead>
                        <tr>
                            <th><?php _e('Claim Number', 'prize-claim'); ?></th>
                            <th><?php _e('Tournament', 'prize-claim'); ?></th>
                            <th><?php _e('Amount', 'prize-claim'); ?></th>
                            <th><?php _e('Status', 'prize-claim'); ?></th>
                            <th><?php _e('Submitted', 'prize-claim'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($claims as $claim): ?>
                            <tr>
                                <td><?php echo esc_html($claim->claim_number); ?></td>
                                <td><?php echo esc_html($claim->tournament_name); ?></td>
                                <td><?php echo esc_html(number_format($claim->amount, 2)); ?></td>
                                <td>
                                    <span class="pc-status pc-status-<?php echo esc_attr($claim->status); ?>">
                                        <?php echo esc_html(ucfirst($claim->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($claim->submission_date))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_w9_form_url() {
        return $this->db->get_setting('w9_form_url', 'https://nextform.app/form/w9Mar2024/1ndeijALNwy8RXzWvQnGkX');
    }

    private function get_direct_deposit_form_url() {
        return $this->db->get_setting('direct_deposit_form_url', 'https://nextform.app/form/dd2024/default');
    }
}

// Initialize shortcodes
new PrizeClaimShortcodes();