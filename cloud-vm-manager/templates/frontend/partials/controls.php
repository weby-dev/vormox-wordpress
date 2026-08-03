<?php

/**
 * Machine control panel.
 *
 * @package CloudVmManager
 *
 * @var \CloudVmManager\Model\VmOrder $machine        Machine being controlled.
 * @var bool                          $operable       Whether actions may be offered.
 * @var array<int, string>            $rebuildOptions Images available for a rebuild.
 * @var string[]                      $powerActions   Documented power actions.
 */

defined('ABSPATH') || exit;

$cvm_power_labels = [
    'start' => __('Start', 'cloud-vm-manager'),
    'stop' => __('Stop', 'cloud-vm-manager'),
    'reboot' => __('Reboot', 'cloud-vm-manager'),
    'pause' => __('Pause', 'cloud-vm-manager'),
    'hibernate' => __('Hibernate', 'cloud-vm-manager'),
    'resume' => __('Resume', 'cloud-vm-manager'),
];

$cvm_confirmations = [
    'stop' => __('Stop this machine? Anything running on it is interrupted.', 'cloud-vm-manager'),
    'reboot' => __('Reboot this machine now?', 'cloud-vm-manager'),
    'hibernate' => __('Hibernate this machine?', 'cloud-vm-manager'),
];
?>
<section class="cvm-panel cvm-controls">
    <h3><?php esc_html_e('Controls', 'cloud-vm-manager'); ?></h3>

    <?php if (!$operable) : ?>
        <p class="cvm-notice cvm-notice-info">
            <?php esc_html_e('Controls become available once the machine is deployed and unlocked.', 'cloud-vm-manager'); ?>
        </p>
    <?php else : ?>
        <div class="cvm-control-group">
            <h4><?php esc_html_e('Power', 'cloud-vm-manager'); ?></h4>
            <div class="cvm-control-row">
                <?php foreach ($powerActions as $cvm_action) : ?>
                    <button
                        type="button"
                        class="cvm-button cvm-button-secondary cvm-control"
                        data-cvm-action="cvm_vm_power"
                        data-cvm-power="<?php echo esc_attr($cvm_action); ?>"
                        <?php if (isset($cvm_confirmations[$cvm_action])) : ?>
                            data-cvm-confirm="<?php echo esc_attr($cvm_confirmations[$cvm_action]); ?>"
                        <?php endif; ?>
                    >
                        <?php echo esc_html($cvm_power_labels[$cvm_action] ?? $cvm_action); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="cvm-control-group">
            <h4><?php esc_html_e('Administrator password', 'cloud-vm-manager'); ?></h4>
            <div class="cvm-control-row">
                <label class="screen-reader-text" for="cvm-new-password">
                    <?php esc_html_e('New password', 'cloud-vm-manager'); ?>
                </label>
                <input
                    type="password"
                    id="cvm-new-password"
                    class="cvm-input"
                    autocomplete="new-password"
                    placeholder="<?php esc_attr_e('New password', 'cloud-vm-manager'); ?>"
                >
                <button type="button" class="cvm-button cvm-control" data-cvm-action="cvm_vm_password">
                    <?php esc_html_e('Set password', 'cloud-vm-manager'); ?>
                </button>
            </div>
            <p class="cvm-muted">
                <?php esc_html_e('The machine must be running: the password is applied through the guest agent and can take a few seconds.', 'cloud-vm-manager'); ?>
            </p>
        </div>

        <?php if ($rebuildOptions !== []) : ?>
            <div class="cvm-control-group">
                <h4><?php esc_html_e('Reinstall operating system', 'cloud-vm-manager'); ?></h4>
                <div class="cvm-control-row">
                    <label class="screen-reader-text" for="cvm-rebuild-iso">
                        <?php esc_html_e('Operating system', 'cloud-vm-manager'); ?>
                    </label>
                    <select id="cvm-rebuild-iso" class="cvm-select">
                        <?php foreach ($rebuildOptions as $cvm_value => $cvm_label) : ?>
                            <option value="<?php echo esc_attr((string) $cvm_value); ?>"
                                <?php selected($machine->getIsoRemoteId(), $cvm_value); ?>>
                                <?php echo esc_html($cvm_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button
                        type="button"
                        class="cvm-button cvm-button-danger cvm-control"
                        data-cvm-action="cvm_vm_rebuild"
                        data-cvm-confirm="<?php esc_attr_e('Reinstalling wipes every file on this machine. Continue?', 'cloud-vm-manager'); ?>"
                    >
                        <?php esc_html_e('Reinstall', 'cloud-vm-manager'); ?>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <div class="cvm-control-group">
            <h4><?php esc_html_e('Network', 'cloud-vm-manager'); ?></h4>
            <div class="cvm-control-row">
                <button
                    type="button"
                    class="cvm-button cvm-button-secondary cvm-control"
                    data-cvm-action="cvm_vm_regenerate_mac"
                    data-cvm-confirm="<?php esc_attr_e('Generating a new MAC address restarts the machine. Continue?', 'cloud-vm-manager'); ?>"
                >
                    <?php esc_html_e('Regenerate MAC address', 'cloud-vm-manager'); ?>
                </button>
                <button
                    type="button"
                    class="cvm-button cvm-button-secondary cvm-control"
                    data-cvm-action="cvm_vm_reconfigure_network"
                    data-cvm-confirm="<?php esc_attr_e('Re-applying the network configuration restarts the machine. Continue?', 'cloud-vm-manager'); ?>"
                >
                    <?php esc_html_e('Repair network', 'cloud-vm-manager'); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <div class="cvm-control-feedback" role="status" aria-live="polite"></div>
</section>
