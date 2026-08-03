<?php

/**
 * Synchronisation screen.
 *
 * @package CloudVmManager
 *
 * @var array<int, array<string, mixed>>   $rows         Provider rows with their counts.
 * @var \CloudVmManager\Model\SyncRun[]    $runs         Recent synchronisation runs.
 * @var int                                $nextRun      Timestamp of the next scheduled run.
 * @var string                             $providersUrl URL of the providers screen.
 * @var string                             $settingsUrl  URL of the settings screen.
 */

defined('ABSPATH') || exit;

$cvm_resource_labels = [
    'zones' => __('Zones', 'cloud-vm-manager'),
    'isos' => __('OS images', 'cloud-vm-manager'),
    'cpu' => __('CPU', 'cloud-vm-manager'),
    'ram' => __('RAM', 'cloud-vm-manager'),
    'disk' => __('Disk', 'cloud-vm-manager'),
    'bandwidth' => __('Bandwidth', 'cloud-vm-manager'),
    'pricing' => __('Price book', 'cloud-vm-manager'),
];

$cvm_status_labels = [
    'success' => __('Success', 'cloud-vm-manager'),
    'partial' => __('Partial', 'cloud-vm-manager'),
    'failed' => __('Failed', 'cloud-vm-manager'),
    'running' => __('Running', 'cloud-vm-manager'),
];
?>
<div class="wrap cvm-wrap">
    <div class="cvm-page-header">
        <div>
            <h1 class="wp-heading-inline"><?php esc_html_e('Catalogue Synchronisation', 'cloud-vm-manager'); ?></h1>
            <p class="cvm-page-subtitle">
                <?php esc_html_e('Zones, operating system images and pricing tiers are pulled from each provider and stored with their backend identifiers.', 'cloud-vm-manager'); ?>
            </p>
        </div>
        <div class="cvm-page-actions">
            <a href="<?php echo esc_url($providersUrl); ?>" class="button">
                <?php esc_html_e('Providers', 'cloud-vm-manager'); ?>
            </a>
            <a href="<?php echo esc_url($settingsUrl); ?>" class="button">
                <?php esc_html_e('Settings', 'cloud-vm-manager'); ?>
            </a>
        </div>
    </div>

    <div class="cvm-card">
        <h2 class="cvm-card-title"><?php esc_html_e('Scheduled run', 'cloud-vm-manager'); ?></h2>
        <p class="cvm-card-subtitle">
            <?php if ($nextRun > 0) : ?>
                <?php
                printf(
                    /* translators: %s: human readable time difference. */
                    esc_html__('The next automatic synchronisation runs in %s.', 'cloud-vm-manager'),
                    esc_html(human_time_diff(time(), $nextRun))
                );
                ?>
            <?php else : ?>
                <?php esc_html_e('No automatic synchronisation is scheduled.', 'cloud-vm-manager'); ?>
            <?php endif; ?>
        </p>
    </div>

    <?php if ($rows === []) : ?>
        <div class="cvm-card cvm-empty-state">
            <h2><?php esc_html_e('Nothing to synchronise yet', 'cloud-vm-manager'); ?></h2>
            <p><?php esc_html_e('Add a cloud provider first, then run a synchronisation to pull its catalogue.', 'cloud-vm-manager'); ?></p>
            <a href="<?php echo esc_url($providersUrl); ?>" class="button button-primary button-hero">
                <?php esc_html_e('Add a provider', 'cloud-vm-manager'); ?>
            </a>
        </div>
    <?php else : ?>
        <div class="cvm-card">
            <h2 class="cvm-card-title"><?php esc_html_e('Stored catalogue', 'cloud-vm-manager'); ?></h2>
            <table class="wp-list-table widefat fixed striped cvm-table">
                <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Provider', 'cloud-vm-manager'); ?></th>
                    <?php foreach ($cvm_resource_labels as $cvm_label) : ?>
                        <th scope="col" class="cvm-col-count"><?php echo esc_html($cvm_label); ?></th>
                    <?php endforeach; ?>
                    <th scope="col"><?php esc_html_e('Last run', 'cloud-vm-manager'); ?></th>
                    <th scope="col" class="cvm-col-actions"><?php esc_html_e('Actions', 'cloud-vm-manager'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $cvm_row) : ?>
                    <?php
                    $cvm_provider = $cvm_row['provider'];
                    $cvm_counts = $cvm_row['counts'];
                    $cvm_last = $cvm_row['lastRun'];
                    ?>
                    <tr class="cvm-provider-row" data-provider-id="<?php echo esc_attr((string) $cvm_provider->id()); ?>">
                        <td>
                            <strong><?php echo esc_html($cvm_provider->getName()); ?></strong>
                            <div class="cvm-muted">
                                <span class="cvm-badge cvm-status cvm-status-<?php echo esc_attr($cvm_provider->getStatus()); ?>">
                                    <?php echo esc_html($cvm_provider->getStatus()); ?>
                                </span>
                            </div>
                        </td>
                        <?php foreach (array_keys($cvm_resource_labels) as $cvm_key) : ?>
                            <td class="cvm-col-count"><?php echo esc_html((string) ($cvm_counts[$cvm_key] ?? 0)); ?></td>
                        <?php endforeach; ?>
                        <td>
                            <?php if ($cvm_last !== null) : ?>
                                <span class="cvm-badge cvm-status cvm-status-<?php echo esc_attr($cvm_last->getStatus()); ?>">
                                    <?php echo esc_html($cvm_status_labels[$cvm_last->getStatus()] ?? $cvm_last->getStatus()); ?>
                                </span>
                                <div class="cvm-muted"><?php echo esc_html($cvm_last->getString('started_at')); ?></div>
                            <?php else : ?>
                                <span class="cvm-muted"><?php esc_html_e('Never', 'cloud-vm-manager'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="cvm-col-actions">
                            <div class="cvm-actions">
                                <button type="button" class="button button-primary cvm-action" data-action="cvm_sync_provider">
                                    <?php esc_html_e('Synchronise', 'cloud-vm-manager'); ?>
                                </button>
                            </div>
                            <div class="cvm-action-feedback" role="status" aria-live="polite"></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="cvm-card">
            <h2 class="cvm-card-title"><?php esc_html_e('Recent runs', 'cloud-vm-manager'); ?></h2>
            <?php if ($runs === []) : ?>
                <p class="cvm-card-subtitle"><?php esc_html_e('No synchronisation has run yet.', 'cloud-vm-manager'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped cvm-table">
                    <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Resource', 'cloud-vm-manager'); ?></th>
                        <th scope="col"><?php esc_html_e('Status', 'cloud-vm-manager'); ?></th>
                        <th scope="col" class="cvm-col-count"><?php esc_html_e('Added', 'cloud-vm-manager'); ?></th>
                        <th scope="col" class="cvm-col-count"><?php esc_html_e('Updated', 'cloud-vm-manager'); ?></th>
                        <th scope="col" class="cvm-col-count"><?php esc_html_e('Removed', 'cloud-vm-manager'); ?></th>
                        <th scope="col" class="cvm-col-count"><?php esc_html_e('Unchanged', 'cloud-vm-manager'); ?></th>
                        <th scope="col"><?php esc_html_e('Started', 'cloud-vm-manager'); ?></th>
                        <th scope="col"><?php esc_html_e('Message', 'cloud-vm-manager'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($runs as $cvm_run) : ?>
                        <tr>
                            <td><?php echo esc_html($cvm_run->getResource()); ?></td>
                            <td>
                                <span class="cvm-badge cvm-status cvm-status-<?php echo esc_attr($cvm_run->getStatus()); ?>">
                                    <?php echo esc_html($cvm_status_labels[$cvm_run->getStatus()] ?? $cvm_run->getStatus()); ?>
                                </span>
                            </td>
                            <td class="cvm-col-count"><?php echo esc_html((string) $cvm_run->getAdded()); ?></td>
                            <td class="cvm-col-count"><?php echo esc_html((string) $cvm_run->getUpdated()); ?></td>
                            <td class="cvm-col-count"><?php echo esc_html((string) $cvm_run->getRemoved()); ?></td>
                            <td class="cvm-col-count"><?php echo esc_html((string) $cvm_run->getUnchanged()); ?></td>
                            <td class="cvm-muted"><?php echo esc_html($cvm_run->getString('started_at')); ?></td>
                            <td class="cvm-muted"><?php echo esc_html($cvm_run->getMessage()); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
