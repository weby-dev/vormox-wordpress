<?php

/**
 * Cloud virtual machine product panel.
 *
 * @package CloudVmManager
 *
 * @var \CloudVmManager\WooCommerce\ProductConfiguration $configuration Stored configuration.
 * @var \CloudVmManager\Model\Provider[]                 $providers     Available providers.
 * @var array<int, string>                               $zones         Zone options.
 * @var array<int, string>                               $isos          Image options.
 * @var array<string, array<int, array<string, mixed>>>  $plans         Plan options per resource.
 * @var array<string, string>                            $billingCycles Billing cycle options.
 * @var array<string, string>                            $planTypes     Plan type options.
 * @var \CloudVmManager\WooCommerce\PriceBreakdown       $breakdown     Current pricing.
 * @var string                                           $currency      Store currency code.
 */

defined('ABSPATH') || exit;

$cvm_resource_labels = [
    'cpu' => __('CPU', 'cloud-vm-manager'),
    'ram' => __('RAM', 'cloud-vm-manager'),
    'disk' => __('Disk', 'cloud-vm-manager'),
    'bandwidth' => __('Bandwidth', 'cloud-vm-manager'),
];
?>
<div id="cvm_product_data" class="panel woocommerce_options_panel hidden">
    <?php if ($providers === []) : ?>
        <div class="options_group">
            <p class="cvm-panel-notice">
                <?php esc_html_e('Add a cloud provider and synchronise its catalogue before configuring this product.', 'cloud-vm-manager'); ?>
            </p>
        </div>
    <?php else : ?>
        <div class="options_group">
            <p class="form-field">
                <label for="cvm_provider_id"><?php esc_html_e('Provider', 'cloud-vm-manager'); ?></label>
                <select name="cvm_provider_id" id="cvm_provider_id" class="select short cvm-reloads-catalogue">
                    <?php foreach ($providers as $cvm_provider) : ?>
                        <option value="<?php echo esc_attr((string) $cvm_provider->id()); ?>"
                            <?php selected($configuration->providerId(), $cvm_provider->id()); ?>>
                            <?php echo esc_html($cvm_provider->getName()); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="description"><?php esc_html_e('Backend that provisions and bills this machine.', 'cloud-vm-manager'); ?></span>
            </p>

            <p class="form-field">
                <label for="cvm_plan_type"><?php esc_html_e('Plan type', 'cloud-vm-manager'); ?></label>
                <select name="cvm_plan_type" id="cvm_plan_type" class="select short cvm-reloads-catalogue">
                    <?php foreach ($planTypes as $cvm_value => $cvm_label) : ?>
                        <option value="<?php echo esc_attr((string) $cvm_value); ?>"
                            <?php selected($configuration->planType(), $cvm_value); ?>>
                            <?php echo esc_html($cvm_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p class="form-field">
                <label for="cvm_zone_remote_id"><?php esc_html_e('Zone', 'cloud-vm-manager'); ?></label>
                <select name="cvm_zone_remote_id" id="cvm_zone_remote_id" class="select short cvm-reloads-images">
                    <option value="0"><?php esc_html_e('— Select a zone —', 'cloud-vm-manager'); ?></option>
                    <?php foreach ($zones as $cvm_value => $cvm_label) : ?>
                        <option value="<?php echo esc_attr((string) $cvm_value); ?>"
                            <?php selected($configuration->zoneRemoteId(), $cvm_value); ?>>
                            <?php echo esc_html($cvm_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p class="form-field">
                <label for="cvm_iso_remote_id"><?php esc_html_e('Operating system', 'cloud-vm-manager'); ?></label>
                <select name="cvm_iso_remote_id" id="cvm_iso_remote_id" class="select short">
                    <option value="0"><?php esc_html_e('— Select an image —', 'cloud-vm-manager'); ?></option>
                    <?php foreach ($isos as $cvm_value => $cvm_label) : ?>
                        <option value="<?php echo esc_attr((string) $cvm_value); ?>"
                            <?php selected($configuration->isoRemoteId(), $cvm_value); ?>>
                            <?php echo esc_html($cvm_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
        </div>

        <div class="options_group">
            <?php foreach ($cvm_resource_labels as $cvm_resource => $cvm_label) : ?>
                <p class="form-field">
                    <label for="cvm_<?php echo esc_attr($cvm_resource); ?>_price_id">
                        <?php echo esc_html($cvm_label); ?>
                    </label>
                    <select
                        name="cvm_<?php echo esc_attr($cvm_resource); ?>_price_id"
                        id="cvm_<?php echo esc_attr($cvm_resource); ?>_price_id"
                        class="select short cvm-plan-select"
                        data-resource="<?php echo esc_attr($cvm_resource); ?>"
                    >
                        <option value="0"><?php esc_html_e('— Select a tier —', 'cloud-vm-manager'); ?></option>
                        <?php foreach (($plans[$cvm_resource] ?? []) as $cvm_value => $cvm_plan) : ?>
                            <option
                                value="<?php echo esc_attr((string) $cvm_value); ?>"
                                data-provider-price="<?php echo esc_attr((string) $cvm_plan['provider']); ?>"
                                data-selling-price="<?php echo esc_attr((string) $cvm_plan['selling']); ?>"
                                <?php selected($configuration->priceId($cvm_resource), $cvm_value); ?>
                            >
                                <?php echo esc_html((string) $cvm_plan['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>
            <?php endforeach; ?>
        </div>

        <div class="options_group">
            <p class="form-field">
                <label for="cvm_billing_cycle"><?php esc_html_e('Billing cycle', 'cloud-vm-manager'); ?></label>
                <select name="cvm_billing_cycle" id="cvm_billing_cycle" class="select short">
                    <?php foreach ($billingCycles as $cvm_value => $cvm_label) : ?>
                        <option value="<?php echo esc_attr((string) $cvm_value); ?>"
                            <?php selected($configuration->billingCycle(), $cvm_value); ?>>
                            <?php echo esc_html($cvm_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="description"><?php esc_html_e('Monthly tier prices are multiplied by the months in the cycle.', 'cloud-vm-manager'); ?></span>
            </p>

            <p class="form-field">
                <label for="cvm_price_mode"><?php esc_html_e('Pricing', 'cloud-vm-manager'); ?></label>
                <select name="cvm_price_mode" id="cvm_price_mode" class="select short">
                    <option value="auto" <?php selected($configuration->priceMode(), 'auto'); ?>>
                        <?php esc_html_e('Calculated from the price book', 'cloud-vm-manager'); ?>
                    </option>
                    <option value="manual" <?php selected($configuration->priceMode(), 'manual'); ?>>
                        <?php esc_html_e('Fixed price', 'cloud-vm-manager'); ?>
                    </option>
                </select>
            </p>

            <p class="form-field cvm-manual-price">
                <label for="cvm_manual_price">
                    <?php
                    printf(
                        /* translators: %s: currency code. */
                        esc_html__('Fixed price (%s)', 'cloud-vm-manager'),
                        esc_html($currency)
                    );
                    ?>
                </label>
                <input type="text" name="cvm_manual_price" id="cvm_manual_price" class="short wc_input_price"
                       value="<?php echo esc_attr((string) $configuration->manualPrice()); ?>">
            </p>

            <p class="form-field">
                <label for="cvm_allow_coupons"><?php esc_html_e('Coupons', 'cloud-vm-manager'); ?></label>
                <input type="checkbox" name="cvm_allow_coupons" id="cvm_allow_coupons" class="checkbox" value="1"
                    <?php checked($configuration->allowsCoupons()); ?>>
                <span class="description">
                    <?php esc_html_e('Forward the applied WooCommerce coupon code to the provider when the machine is created.', 'cloud-vm-manager'); ?>
                </span>
            </p>
        </div>

        <div class="options_group cvm-summary">
            <h4><?php esc_html_e('Margin', 'cloud-vm-manager'); ?></h4>
            <table class="cvm-summary-table">
                <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e('Provider cost', 'cloud-vm-manager'); ?></th>
                    <td><span data-cvm-summary="provider_cost"><?php echo esc_html((string) $breakdown->providerCost()); ?></span></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Selling price', 'cloud-vm-manager'); ?></th>
                    <td><span data-cvm-summary="selling_price"><?php echo esc_html((string) $breakdown->sellingPrice()); ?></span></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Markup', 'cloud-vm-manager'); ?></th>
                    <td>
                        <span data-cvm-summary="markup"><?php echo esc_html((string) $breakdown->markup()); ?></span>
                        (<span data-cvm-summary="markup_percent"><?php echo esc_html((string) $breakdown->markupPercent()); ?></span>%)
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Profit margin', 'cloud-vm-manager'); ?></th>
                    <td><span data-cvm-summary="margin_percent"><?php echo esc_html((string) $breakdown->marginPercent()); ?></span>%</td>
                </tr>
                </tbody>
            </table>
            <?php if (!$breakdown->isComplete()) : ?>
                <p class="cvm-panel-notice">
                    <?php
                    printf(
                        /* translators: %s: comma separated list of resources. */
                        esc_html__('Still to configure: %s', 'cloud-vm-manager'),
                        esc_html(implode(', ', $breakdown->missing()))
                    );
                    ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
