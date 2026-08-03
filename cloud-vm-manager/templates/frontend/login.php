<?php

/**
 * Signed out dashboard placeholder.
 *
 * @package CloudVmManager
 *
 * @var string $loginUrl URL of the sign in form.
 */

defined('ABSPATH') || exit;
?>
<div class="cvm-dashboard">
    <div class="cvm-panel cvm-empty">
        <h3><?php esc_html_e('Sign in to manage your machines', 'cloud-vm-manager'); ?></h3>
        <p><?php esc_html_e('Your virtual machines, metrics and invoices are available once you are signed in.', 'cloud-vm-manager'); ?></p>
        <a class="cvm-button" href="<?php echo esc_url($loginUrl); ?>">
            <?php esc_html_e('Sign in', 'cloud-vm-manager'); ?>
        </a>
    </div>
</div>
