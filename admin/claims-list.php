<?php
/**
 * Prize Claims List Template
 * Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-02-20 00:59:18
 * Current User's Login: EvThatGuy
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get filters from URL
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'pending';
$date_filter = isset($_GET['date_range']) ? sanitize_text_field($_GET['date_range']) : '30days';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Prize Claims', 'prize-claim'); ?></h1>
    
    <!-- Filters -->
    <div class="tablenav top">
        <form method="get" class="alignleft actions">
            <input type="hidden" name="page" value="prize-claims">
            
            <!-- Status Filter -->
            <select name="status">
                <option value="all" <?php selected($status_filter, 'all'); ?>><?php _e('All Statuses', 'prize-claim'); ?></option>
                <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php _e('Pending', 'prize-claim'); ?></option>
                <option value="w9_pending" <?php selected($status_filter, 'w9_pending'); ?>><?php _e('W9 Pending', 'prize-claim'); ?></option>
                <option value="processing" <?php selected($status_filter, 'processing'); ?>><?php _e('Processing', 'prize-claim'); ?></option>
                <option value="ready_to_pay" <?php selected($status_filter, 'ready_to_pay'); ?>><?php _e('Ready to Pay', 'prize-claim'); ?></option>
                <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php _e('Completed', 'prize-claim'); ?></option>
                <option value="closed" <?php selected($status_filter, 'closed'); ?>><?php _e('Closed', 'prize-claim'); ?></option>
            </select>
            
            <!-- Date Range Filter -->
            <select name="date_range">
                <option value="7days" <?php selected($date_filter, '7days'); ?>><?php _e('Last 7 Days', 'prize-claim'); ?></option>
                <option value="30days" <?php selected($date_filter, '30days'); ?>><?php _e('Last 30 Days', 'prize-claim'); ?></option>
                <option value="90days" <?php selected($date_filter, '90days'); ?>><?php _e('Last 90 Days', 'prize-claim'); ?></option>
                <option value="all" <?php selected($date_filter, 'all'); ?>><?php _e('All Time', 'prize-claim'); ?></option>
            </select>
            
            <!-- Search Box -->
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search claims...', 'prize-claim'); ?>">
            
            <?php submit_button(__('Filter', 'prize-claim'), 'action', '', false); ?>
        </form>
        
        <!-- Bulk Actions -->
        <div class="alignleft actions bulkactions">
            <select name="bulk_action">
                <option value="-1"><?php _e('Bulk Actions', 'prize-claim'); ?></option>
                <option value="verify_w9"><?php _e('Verify W9', 'prize-claim'); ?></option>
                <option value="verify_dd"><?php _e('Verify Direct Deposit', 'prize-claim'); ?></option>
                <option value="mark_ready"><?php _e('Mark Ready to Pay', 'prize-claim'); ?></option>
                <option value="process_payment"><?php _e('Process Payment', 'prize-claim'); ?></option>
                <option value="close"><?php _e('Close Claims', 'prize-claim'); ?></option>
            </select>
            <?php submit_button(__('Apply', 'prize-claim'), 'action', 'do_bulk_action', false); ?>
        </div>
    </div>
        <!-- Claims Table -->
        <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <td class="manage-column column-cb check-column">
                    <input type="checkbox" id="cb-select-all-1">
                </td>
                <th scope="col" class="manage-column column-claim_number">
                    <?php _e('Claim #', 'prize-claim'); ?>
                </th>
                <th scope="col" class="manage-column column-user">
                    <?php _e('User', 'prize-claim'); ?>
                </th>
                <th scope="col" class="manage-column column-tournament">
                    <?php _e('Tournament', 'prize-claim'); ?>
                </th>
                <th scope="col" class="manage-column column-amount">
                    <?php _e('Amount', 'prize-claim'); ?>
                </th>
                <th scope="col" class="manage-column column-w9">
                    <?php _e('W9 Status', 'prize-claim'); ?>
                </th>
                <th scope="col" class="manage-column column-dd">
                    <?php _e('Direct Deposit', 'prize-claim'); ?>
                </th>
                <th scope="col" class="manage-column column-status">
                    <?php _e('Status', 'prize-claim'); ?>
                </th>
                <th scope="col" class="manage-column column-date">
                    <?php _e('Submitted', 'prize-claim'); ?>
                </th>
                <th scope="col" class="manage-column column-actions">
                    <?php _e('Actions', 'prize-claim'); ?>
                </th>
            </tr>
        </thead>
        
        <tbody id="the-list">
            <?php foreach ($claims as $claim): ?>
                <tr id="claim-<?php echo esc_attr($claim->id); ?>">
                    <th scope="row" class="check-column">
                        <input type="checkbox" name="claim[]" value="<?php echo esc_attr($claim->id); ?>">
                    </th>
                    <td class="column-claim_number">
                        <strong>
                            <a href="#" class="claim-details" data-id="<?php echo esc_attr($claim->id); ?>">
                                <?php echo esc_html($claim->claim_number); ?>
                            </a>
                        </strong>
                    </td>
                    <td class="column-user">
                        <?php echo esc_html($claim->user_name); ?>
                    </td>
                    <td class="column-tournament">
                        <?php echo esc_html($claim->tournament_name); ?>
                    </td>
                    <td class="column-amount">
                        <?php echo '$' . number_format($claim->amount, 2); ?>
                    </td>
                    <td class="column-w9">
                        <span class="status-<?php echo esc_attr($claim->w9_status); ?>">
                            <?php echo esc_html(ucfirst($claim->w9_status)); ?>
                        </span>
                    </td>
                    <td class="column-dd">
                        <span class="status-<?php echo esc_attr($claim->direct_deposit_status); ?>">
                            <?php echo esc_html(ucfirst($claim->direct_deposit_status)); ?>
                        </span>
                    </td>
                    <td class="column-status">
                        <span class="status-<?php echo esc_attr($claim->status); ?>">
                            <?php echo esc_html(ucfirst($claim->status)); ?>
                        </span>
                    </td>
                    <td class="column-date">
                        <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($claim->submission_date))); ?>
                    </td>
                    <td class="column-actions">
                        <?php if ($claim->status !== 'completed' && $claim->status !== 'closed'): ?>
                            <div class="row-actions">
                                <?php if ($claim->w9_status === 'pending' && $claim->amount >= get_option('pc_w9_required_amount', 600)): ?>
                                    <span class="verify-w9">
                                        <a href="#" class="verify-w9-link" data-id="<?php echo esc_attr($claim->id); ?>">
                                            <?php _e('Verify W9', 'prize-claim'); ?>
                                        </a> |
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($claim->direct_deposit_status === 'pending'): ?>
                                    <span class="verify-dd">
                                        <a href="#" class="verify-dd-link" data-id="<?php echo esc_attr($claim->id); ?>">
                                            <?php _e('Verify DD', 'prize-claim'); ?>
                                        </a> |
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($claim->status === 'ready_to_pay'): ?>
                                    <span class="process-payment">
                                        <a href="#" class="process-payment-link" data-id="<?php echo esc_attr($claim->id); ?>">
                                            <?php _e('Process Payment', 'prize-claim'); ?>
                                        </a> |
                                    </span>
                                <?php endif; ?>
                                
                                <span class="close">
                                    <a href="#" class="close-claim-link" data-id="<?php echo esc_attr($claim->id); ?>">
                                        <?php _e('Close', 'prize-claim'); ?>
                                    </a>
                                </span>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            
            <?php if (empty($claims)): ?>
                <tr>
                    <td colspan="10" class="no-items">
                        <?php _e('No claims found.', 'prize-claim'); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td class="manage-column column-cb check-column">
                    <input type="checkbox" id="cb-select-all-2">
                </td>
                <th scope="col" class="manage-column column-claim_number"><?php _e('Claim #', 'prize-claim'); ?></th>
                <th scope="col" class="manage-column column-user"><?php _e('User', 'prize-claim'); ?></th>
                <th scope="col" class="manage-column column-tournament"><?php _e('Tournament', 'prize-claim'); ?></th>
                <th scope="col" class="manage-column column-amount"><?php _e('Amount', 'prize-claim'); ?></th>
                <th scope="col" class="manage-column column-w9"><?php _e('W9 Status', 'prize-claim'); ?></th>
                <th scope="col" class="manage-column column-dd"><?php _e('Direct Deposit', 'prize-claim'); ?></th>
                <th scope="col" class="manage-column column-status"><?php _e('Status', 'prize-claim'); ?></th>
                <th scope="col" class="manage-column column-date"><?php _e('Submitted', 'prize-claim'); ?></th>
                <th scope="col" class="manage-column column-actions"><?php _e('Actions', 'prize-claim'); ?></th>
            </tr>
        </tfoot>
    </table>
</div>
<!-- Modal Templates -->
<div id="verify-w9-modal" class="pc-modal" style="display:none;">
    <div class="pc-modal-content">
        <h3><?php _e('Verify W9', 'prize-claim'); ?></h3>
        <form id="verify-w9-form">
            <input type="hidden" name="claim_id" value="">
            <div class="form-field">
                <label><?php _e('W9 Status', 'prize-claim'); ?></label>
                <select name="w9_verification" required>
                    <option value="verified"><?php _e('Verified', 'prize-claim'); ?></option>
                    <option value="rejected"><?php _e('Rejected', 'prize-claim'); ?></option>
                </select>
            </div>
            <div class="form-field">
                <label><?php _e('Notes (Optional)', 'prize-claim'); ?></label>
                <textarea name="verification_notes" rows="4" placeholder="<?php _e('Enter any notes about the verification...', 'prize-claim'); ?>"></textarea>
            </div>
            <div class="pc-modal-buttons">
                <button type="submit" class="button button-primary"><?php _e('Submit', 'prize-claim'); ?></button>
                <button type="button" class="button pc-modal-close"><?php _e('Cancel', 'prize-claim'); ?></button>
            </div>
        </form>
    </div>
</div>

<div id="verify-dd-modal" class="pc-modal" style="display:none;">
    <div class="pc-modal-content">
        <h3><?php _e('Verify Direct Deposit', 'prize-claim'); ?></h3>
        <form id="verify-dd-form">
            <input type="hidden" name="claim_id" value="">
            <div class="form-field">
                <label><?php _e('Bank Name', 'prize-claim'); ?></label>
                <input type="text" name="bank_name" required>
            </div>
            <div class="form-field">
                <label><?php _e('Account Type', 'prize-claim'); ?></label>
                <select name="account_type" required>
                    <option value="checking"><?php _e('Checking', 'prize-claim'); ?></option>
                    <option value="savings"><?php _e('Savings', 'prize-claim'); ?></option>
                </select>
            </div>
            <div class="form-field">
                <label><?php _e('Last 4 of Routing #', 'prize-claim'); ?></label>
                <input type="text" name="routing_last4" pattern="\d{4}" maxlength="4" required>
            </div>
            <div class="form-field">
                <label><?php _e('Last 4 of Account #', 'prize-claim'); ?></label>
                <input type="text" name="account_last4" pattern="\d{4}" maxlength="4" required>
            </div>
            <div class="pc-modal-buttons">
                <button type="submit" class="button button-primary"><?php _e('Verify', 'prize-claim'); ?></button>
                <button type="button" class="button pc-modal-close"><?php _e('Cancel', 'prize-claim'); ?></button>
            </div>
        </form>
    </div>
</div>
<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize W9 verification modal
    $('.verify-w9-link').click(function(e) {
        e.preventDefault();
        var claimId = $(this).data('id');
        $('#verify-w9-modal input[name="claim_id"]').val(claimId);
        // Clear previous form values
        $('#verify-w9-form select[name="w9_verification"]').val('verified');
        $('#verify-w9-form textarea[name="verification_notes"]').val('');
        $('#verify-w9-modal').show();
    });

    // Handle W9 verification form submission
    $('#verify-w9-form').submit(function(e) {
        e.preventDefault();
        var formData = {
            action: 'pc_verify_w9',
            claim_id: $('input[name="claim_id"]').val(),
            w9_status: $('select[name="w9_verification"]').val(),
            verification_notes: $('textarea[name="verification_notes"]').val(),
            nonce: pcAjax.nonce
        };

        $.post(ajaxurl, formData, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || 'Verification failed');
            }
        });
    });

    // Initialize Direct Deposit verification modal
    $('.verify-dd-link').click(function(e) {
        e.preventDefault();
        var claimId = $(this).data('id');
        $('#verify-dd-modal input[name="claim_id"]').val(claimId);
        // Clear previous form values
        $('#verify-dd-form input[name="bank_name"]').val('');
        $('#verify-dd-form select[name="account_type"]').val('checking');
        $('#verify-dd-form input[name="routing_last4"]').val('');
        $('#verify-dd-form input[name="account_last4"]').val('');
        $('#verify-dd-modal').show();
    });

    // Handle Direct Deposit verification form submission
    $('#verify-dd-form').submit(function(e) {
        e.preventDefault();
        var formData = {
            action: 'pc_verify_dd',
            claim_id: $('input[name="claim_id"]').val(),
            bank_name: $('input[name="bank_name"]').val(),
            account_type: $('select[name="account_type"]').val(),
            routing_last4: $('input[name="routing_last4"]').val(),
            account_last4: $('input[name="account_last4"]').val(),
            nonce: pcAjax.nonce
        };

        $.post(ajaxurl, formData, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || 'Verification failed');
            }
        });
    });

    // Handle process payment link
    $('.process-payment-link').click(function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to mark this payment as processed?')) {
            return;
        }

        var claimId = $(this).data('id');
        var formData = {
            action: 'pc_process_payment',
            claim_id: claimId,
            nonce: pcAjax.nonce
        };

        $.post(ajaxurl, formData, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || 'Payment processing failed');
            }
        });
    });

    // Handle close claim link
    $('.close-claim-link').click(function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to close this claim?')) {
            return;
        }

        var claimId = $(this).data('id');
        var formData = {
            action: 'pc_close_claim',
            claim_id: claimId,
            nonce: pcAjax.nonce
        };

        $.post(ajaxurl, formData, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || 'Failed to close claim');
            }
        });
    });

    // Handle modal close buttons
    $('.pc-modal-close').click(function() {
        $(this).closest('.pc-modal').hide();
    });

    // Close modal when clicking outside
    $(window).click(function(e) {
        if ($(e.target).hasClass('pc-modal')) {
            $('.pc-modal').hide();
        }
    });

    // Handle bulk actions
    $('#do_bulk_action').click(function(e) {
        e.preventDefault();
        var action = $('select[name="bulk_action"]').val();
        var claims = $('input[name="claim[]"]:checked').map(function() {
            return $(this).val();
        }).get();

        if (action === '-1') {
            alert('Please select an action');
            return;
        }

        if (claims.length === 0) {
            alert('Please select at least one claim');
            return;
        }

        // Confirm bulk action
        if (!confirm('Are you sure you want to ' + action.replace('_', ' ') + ' the selected claims?')) {
            return;
        }

        var formData = {
            action: 'pc_bulk_action',
            bulk_action: action,
            claims: claims,
            nonce: pcAjax.nonce
        };

        $.post(ajaxurl, formData, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || 'Bulk action failed');
            }
        });
    });

    // Select all checkboxes
    $('#cb-select-all-1, #cb-select-all-2').change(function() {
        var isChecked = $(this).prop('checked');
        $('input[name="claim[]"]').prop('checked', isChecked);
    });

    // Handle enter key in search box
    $('input[name="s"]').keypress(function(e) {
        if (e.which == 13) {
            e.preventDefault();
            $(this).closest('form').submit();
        }
    });
});
</script>