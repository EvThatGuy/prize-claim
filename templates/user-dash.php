<?php
/**
 * Prize Claim - User Dashboard Template
 * Current UTC Time: 2025-02-20 00:44:25
 * Current User: EvThatGuy
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get user's claims and notifications
$user_id = get_current_user_id();
$active_claims = apply_filters('pc_get_user_claims', $user_id);
$notifications = apply_filters('pc_get_user_notifications', $user_id, 5);

// Get W9 threshold amount and form link
$w9_required_amount = apply_filters('pc_get_w9_threshold', 600.00);
$w9_form_link = 'https://nextform.app/form/w9Mar2024/1ndeijALNwy8RXzWvQnGkX';
?>

<div class="prize-claim-dashboard">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <h2><?php esc_html_e('Prize Claim Dashboard', 'prize-claim'); ?></h2>
        <div class="notification-bell">
            <?php 
            $unread = apply_filters('pc_count_unread_notifications', $user_id);
            if ($unread > 0): 
            ?>
            <span class="notification-count"><?php echo esc_html($unread); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Active Claims Section -->
    <div class="active-claims-section">
        <h3><?php esc_html_e('Active Claims', 'prize-claim'); ?></h3>
        <?php if ($active_claims): ?>
            <?php foreach ($active_claims as $claim): ?>
                <div class="claim-card" data-claim-id="<?php echo esc_attr($claim->id); ?>">
                    <div class="claim-header">
                        <h4><?php echo esc_html(sprintf(__('Claim #%s', 'prize-claim'), $claim->claim_number)); ?></h4>
                        <span class="claim-status <?php echo esc_attr($claim->status); ?>">
                            <?php echo esc_html(ucfirst($claim->status)); ?>
                        </span>
                    </div>
                    
                    <div class="claim-details">
                        <div class="claim-amount">
                            <strong><?php esc_html_e('Amount:', 'prize-claim'); ?></strong> 
                            $<?php echo esc_html(number_format($claim->amount, 2)); ?>
                        </div>
                        <div class="claim-date">
                            <strong><?php esc_html_e('Submitted:', 'prize-claim'); ?></strong> 
                            <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($claim->submission_date))); ?>
                        </div>
                    </div>

                    <!-- Progress Tracker -->
                    <div class="claim-progress">
                        <!-- Step 1: Claim Submitted -->
                        <div class="progress-step <?php echo $claim->status != 'pending' ? 'complete' : 'current'; ?>">
                            <div class="step-icon">1</div>
                            <div class="step-label"><?php esc_html_e('Claim Submitted', 'prize-claim'); ?></div>
                            <?php if ($claim->status === 'pending'): ?>
                                <button class="edit-claim-btn" data-claim-id="<?php echo esc_attr($claim->id); ?>">
                                    <?php esc_html_e('Edit Claim', 'prize-claim'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Step 2: W9 Form (Only show if amount exceeds threshold) -->
                        <?php if (floatval($claim->amount) >= $w9_required_amount): ?>
                        <div class="progress-step <?php echo $claim->w9_status === 'verified' ? 'complete' : ($claim->w9_status === 'pending' ? 'current' : ''); ?>">
                            <div class="step-icon">2</div>
                            <div class="step-label"><?php esc_html_e('W9 Form', 'prize-claim'); ?></div>
                            <?php if ($claim->w9_status !== 'verified'): ?>
                                <div class="w9-notice">
                                    <?php 
                                    echo sprintf(
                                        esc_html__('Please complete the W9 form here: %s', 'prize-claim'),
                                        '<a href="' . esc_url($w9_form_link) . '" target="_blank">' . esc_html__('W9 Form Link', 'prize-claim') . '</a>'
                                    ); 
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Step 3: Direct Deposit -->
                        <div class="progress-step <?php echo $claim->direct_deposit_status === 'verified' ? 'complete' : ($claim->direct_deposit_status === 'pending' ? 'current' : ''); ?>">
                            <div class="step-icon"><?php echo floatval($claim->amount) >= $w9_required_amount ? '3' : '2'; ?></div>
                            <div class="step-label"><?php esc_html_e('Direct Deposit', 'prize-claim'); ?></div>
                            <?php if ($claim->direct_deposit_status !== 'verified'): ?>
                                <button class="setup-direct-deposit" data-claim-id="<?php echo esc_attr($claim->id); ?>">
                                    <?php esc_html_e('Setup Direct Deposit', 'prize-claim'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Step 4: Payment Processing -->
                        <div class="progress-step <?php echo $claim->status === 'completed' ? 'complete' : ''; ?>">
                            <div class="step-icon"><?php echo floatval($claim->amount) >= $w9_required_amount ? '4' : '3'; ?></div>
                            <div class="step-label"><?php esc_html_e('Payment Processing', 'prize-claim'); ?></div>
                            <?php if ($claim->payment_date): ?>
                                <div class="payment-date">
                                    <?php echo esc_html(sprintf(
                                        __('Expected: %s', 'prize-claim'),
                                        date_i18n(get_option('date_format'), strtotime($claim->payment_date))
                                    )); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($claim->notes): ?>
                    <div class="claim-notes">
                        <strong><?php esc_html_e('Notes:', 'prize-claim'); ?></strong>
                        <p><?php echo esc_html($claim->notes); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-claims">
                <p><?php esc_html_e('You have no active prize claims.', 'prize-claim'); ?></p>
                <a href="<?php echo esc_url(home_url('/submit-prize-claim')); ?>" class="button new-claim-button">
                    <?php esc_html_e('Submit New Claim', 'prize-claim'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Notifications Section -->
    <div class="notifications-section">
        <h3><?php esc_html_e('Recent Updates', 'prize-claim'); ?></h3>
        <?php if ($notifications): ?>
            <div class="notification-list">
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item <?php echo $notification->is_read ? 'read' : 'unread'; ?>"
                         data-notification-id="<?php echo esc_attr($notification->id); ?>">
                        <div class="notification-header">
                            <span class="notification-type">
                                <?php echo esc_html(ucfirst($notification->type)); ?>
                            </span>
                            <span class="notification-date">
                                <?php echo esc_html(sprintf(
                                    __('%s ago', 'prize-claim'),
                                    human_time_diff(strtotime($notification->created_at), current_time('timestamp'))
                                )); ?>
                            </span>
                        </div>
                        <div class="notification-message">
                            <?php echo wp_kses_post($notification->message); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p><?php esc_html_e('No recent notifications', 'prize-claim'); ?></p>
        <?php endif; ?>
    </div>
</div>

<?php
// Only load modals if user has claims
if ($active_claims):
?>
<!-- Edit Claim Modal -->
<div id="edit-claim-modal" class="pc-modal" style="display: none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3><?php esc_html_e('Edit Prize Claim', 'prize-claim'); ?></h3>
        <?php 
        if (function_exists('gravity_form')) {
            gravity_form(PC_PRIZE_FORM_ID, false, false, false, null, true);
        }
        ?>
    </div>
</div>

<!-- Direct Deposit Modal -->
<div id="direct-deposit-modal" class="pc-modal" style="display: none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3><?php esc_html_e('Setup Direct Deposit', 'prize-claim'); ?></h3>
        <?php 
        if (function_exists('gravity_form')) {
            gravity_form(PC_DEPOSIT_FORM_ID, false, false, false, null, true);
        }
        ?>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize tooltips
    $('.progress-step').tooltip();

    // Modal handling
    $('.edit-claim-btn, .setup-direct-deposit').on('click', function(e) {
        e.preventDefault();
        var modalId = $(this).hasClass('edit-claim-btn') ? 'edit-claim-modal' : 'direct-deposit-modal';
        $('#' + modalId).show();
    });

    $('.close').on('click', function() {
        $(this).closest('.pc-modal').hide();
    });

    $(window).on('click', function(e) {
        if ($(e.target).hasClass('pc-modal')) {
            $('.pc-modal').hide();
        }
    });

    // Mark notifications as read
    $('.notification-item.unread').on('click', function() {
        var $this = $(this);
        var notificationId = $this.data('notification-id');
        
        $.post(pcAjax.url, {
            action: 'pc_mark_notification_read',
            notification_id: notificationId,
            nonce: pcAjax.nonce
        })
        .done(function(response) {
            if (response.success) {
                $this.removeClass('unread').addClass('read');
                updateNotificationCount();
            }
        });
    });

    // Update notification count
    function updateNotificationCount() {
        var $count = $('.notification-count');
        var currentCount = parseInt($count.text());
        if (currentCount > 1) {
            $count.text(currentCount - 1);
        } else {
            $count.remove();
        }
    }

    // Auto-refresh dashboard (every 5 minutes)
    var refreshInterval = setInterval(refreshDashboard, 300000);

    function refreshDashboard() {
        $.post(pcAjax.url, {
            action: 'pc_refresh_dashboard',
            nonce: pcAjax.nonce
        })
        .done(function(response) {
            if (response.success && response.data.hasUpdates) {
                location.reload();
            }
        });
    }
});
</script>
<?php endif; ?>