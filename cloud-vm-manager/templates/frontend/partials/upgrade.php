<?php

/**
 * Machine upgrade panel.
 *
 * @package CloudVmManager
 *
 * @var \CloudVmManager\Model\VmOrder $machine  Machine being upgraded.
 * @var bool                          $operable Whether the flow may be offered.
 * @var array<string, string>         $labels   Resource labels.
 */

defined('ABSPATH') || exit;
?>
<section class="cvm-panel cvm-upgrade" data-cvm-upgrade>
    <h3><?php esc_html_e('Upgrade or renew', 'cloud-vm-manager'); ?></h3>

    <?php if (!$operable) : ?>
        <p class="cvm-notice cvm-notice-info">
            <?php esc_html_e('Upgrades become available once the machine is deployed and unlocked.', 'cloud-vm-manager'); ?>
        </p>
    <?php else : ?>
        <p class="cvm-muted">
            <?php esc_html_e('Pick a larger tier, extend the term, or both. The price comes from the platform and includes the pro rata amount for the time already paid.', 'cloud-vm-manager'); ?>
        </p>

        <div class="cvm-upgrade-grid" data-cvm-upgrade-resources>
            <?php foreach ($labels as $cvm_resource => $cvm_label) : ?>
                <p class="cvm-upgrade-field">
                    <label for="cvm-upgrade-<?php echo esc_attr($cvm_resource); ?>">
                        <?php echo esc_html($cvm_label); ?>
                    </label>
                    <select
                        id="cvm-upgrade-<?php echo esc_attr($cvm_resource); ?>"
                        class="cvm-select cvm-upgrade-select"
                        data-resource="<?php echo esc_attr($cvm_resource); ?>"
                        disabled
                    >
                        <option value="0"><?php esc_html_e('Loading…', 'cloud-vm-manager'); ?></option>
                    </select>
                </p>
            <?php endforeach; ?>

            <p class="cvm-upgrade-field">
                <label for="cvm-upgrade-months"><?php esc_html_e('Extend by', 'cloud-vm-manager'); ?></label>
                <select id="cvm-upgrade-months" class="cvm-select">
                    <option value="0"><?php esc_html_e('Do not extend', 'cloud-vm-manager'); ?></option>
                    <?php foreach ([1, 3, 6, 12] as $cvm_months) : ?>
                        <option value="<?php echo esc_attr((string) $cvm_months); ?>">
                            <?php
                            printf(
                                /* translators: %d: number of months. */
                                esc_html(_n('%d month', '%d months', $cvm_months, 'cloud-vm-manager')),
                                (int) $cvm_months
                            );
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p class="cvm-upgrade-field">
                <label for="cvm-upgrade-coupon"><?php esc_html_e('Coupon', 'cloud-vm-manager'); ?></label>
                <input type="text" id="cvm-upgrade-coupon" class="cvm-input"
                       placeholder="<?php esc_attr_e('Optional', 'cloud-vm-manager'); ?>">
            </p>
        </div>

        <div class="cvm-upgrade-summary" data-cvm-quote hidden>
            <dl class="cvm-specs">
                <div>
                    <dt><?php esc_html_e('Amount', 'cloud-vm-manager'); ?></dt>
                    <dd data-cvm-quote-field="original_amount">—</dd>
                </div>
                <div>
                    <dt><?php esc_html_e('Discount', 'cloud-vm-manager'); ?></dt>
                    <dd data-cvm-quote-field="discount_amount">—</dd>
                </div>
                <div>
                    <dt><?php esc_html_e('Payable', 'cloud-vm-manager'); ?></dt>
                    <dd data-cvm-quote-field="payable_amount">—</dd>
                </div>
                <div>
                    <dt><?php esc_html_e('Coupon', 'cloud-vm-manager'); ?></dt>
                    <dd data-cvm-quote-field="coupon_status">—</dd>
                </div>
            </dl>
        </div>

        <div class="cvm-control-row">
            <button type="button" class="cvm-button cvm-button-secondary" data-cvm-quote-button>
                <?php esc_html_e('Calculate price', 'cloud-vm-manager'); ?>
            </button>
            <button type="button" class="cvm-button" data-cvm-apply-button disabled>
                <?php esc_html_e('Confirm and pay', 'cloud-vm-manager'); ?>
            </button>
        </div>

        <div class="cvm-upgrade-feedback" role="status" aria-live="polite"></div>
    <?php endif; ?>
</section>
