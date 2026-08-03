<?php

/**
 * Unknown machine placeholder.
 *
 * @package CloudVmManager
 *
 * @var string $backUrl URL of the machine list.
 */

defined('ABSPATH') || exit;
?>
<div class="cvm-dashboard">
    <div class="cvm-panel cvm-empty">
        <h3><?php esc_html_e('Machine not available', 'cloud-vm-manager'); ?></h3>
        <p><?php esc_html_e('This machine does not exist, or it is not part of your account.', 'cloud-vm-manager'); ?></p>
        <a class="cvm-button" href="<?php echo esc_url($backUrl); ?>">
            <?php esc_html_e('Back to your machines', 'cloud-vm-manager'); ?>
        </a>
    </div>
</div>
