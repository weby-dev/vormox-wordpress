<?php

/**
 * Admin dashboard.
 *
 * @package CloudVmManager
 *
 * @var array<string, int|float>              $stats     Summary counters.
 * @var \CloudVmManager\Model\Provider[]      $providers Configured providers.
 * @var \CloudVmManager\Model\SyncRun[]       $runs      Recent synchronisation runs.
 * @var \CloudVmManager\Model\LogEntry[]      $logs      Recent log entries.
 * @var array<int, array<string, string>>     $health    Conditions worth attention.
 * @var array<string, int>                    $schedule  Next run per job.
 * @var array<string, string>                 $urls      Links to the other screens.
 */

defined('ABSPATH') || exit;
?>
<div class="wrap cvm-wrap">
    <div class="cvm-page-header">
        <div>
            <h1 class="wp-heading-inline"><?php esc_html_e('Cloud VM Manager', 'cloud-vm-manager'); ?></h1>
            <p class="cvm-page-subtitle">
                <?php esc_html_e('Everything the store knows about its providers, catalogue and machines.', 'cloud-vm-manager'); ?>
            </p>
        </div>
        <div class="cvm-page-actions">
            <a href="<?php echo esc_url($urls['providers']); ?>" class="button"><?php esc_html_e('Providers', 'cloud-vm-manager'); ?></a>
            <a href="<?php echo esc_url($urls['sync']); ?>" class="button"><?php esc_html_e('Synchronisation', 'cloud-vm-manager'); ?></a>
            <a href="<?php echo esc_url($urls['settings']); ?>" class="button"><?php esc_html_e('Settings', 'cloud-vm-manager'); ?></a>
        </div>
    </div>

    <div class="cvm-stat-grid">
        <?php
        $cvm_tiles = [
            ['label' => __('Providers', 'cloud-vm-manager'), 'value' => (string) $stats['providers']],
            ['label' => __('Connected', 'cloud-vm-manager'), 'value' => (string) $stats['connected']],
            ['label' => __('Customers', 'cloud-vm-manager'), 'value' => (string) $stats['customers']],
            ['label' => __('Machines', 'cloud-vm-manager'), 'value' => (string) $stats['machines']],
            ['label' => __('Running', 'cloud-vm-manager'), 'value' => (string) $stats['active']],
            ['label' => __('Provisioning', 'cloud-vm-manager'), 'value' => (string) $stats['provisioning']],
            ['label' => __('Failed', 'cloud-vm-manager'), 'value' => (string) $stats['failed']],
            ['label' => __('Revenue', 'cloud-vm-manager'), 'value' => number_format((float) $stats['revenue'], 2)],
        ];
        ?>
        <?php foreach ($cvm_tiles as $cvm_tile) : ?>
            <div class="cvm-stat-tile">
                <span class="cvm-stat-tile-label"><?php echo esc_html($cvm_tile['label']); ?></span>
                <span class="cvm-stat-tile-value"><?php echo esc_html($cvm_tile['value']); ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="cvm-card">
        <h2 class="cvm-card-title"><?php esc_html_e('Health', 'cloud-vm-manager'); ?></h2>
        <ul class="cvm-health">
            <?php foreach ($health as $cvm_issue) : ?>
                <li class="cvm-health-item cvm-health-<?php echo esc_attr($cvm_issue['level']); ?>">
                    <?php echo esc_html($cvm_issue['message']); ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="cvm-muted">
            <?php if ($schedule['sync'] > 0) : ?>
                <?php
                printf(
                    /* translators: %s: human readable time difference. */
                    esc_html__('Next catalogue synchronisation in %s.', 'cloud-vm-manager'),
                    esc_html(human_time_diff(time(), $schedule['sync']))
                );
                ?>
            <?php else : ?>
                <?php esc_html_e('No catalogue synchronisation is scheduled.', 'cloud-vm-manager'); ?>
            <?php endif; ?>
            <?php if ($schedule['provisioning'] > 0) : ?>
                <?php
                printf(
                    /* translators: %s: human readable time difference. */
                    esc_html__('Next provisioning sweep in %s.', 'cloud-vm-manager'),
                    esc_html(human_time_diff(time(), $schedule['provisioning']))
                );
                ?>
            <?php endif; ?>
        </p>
    </div>

    <div class="cvm-card">
        <h2 class="cvm-card-title"><?php esc_html_e('Providers', 'cloud-vm-manager'); ?></h2>
        <?php if ($providers === []) : ?>
            <p class="cvm-card-subtitle">
                <?php esc_html_e('Add a provider to start selling machines.', 'cloud-vm-manager'); ?>
            </p>
            <a href="<?php echo esc_url($urls['providers']); ?>" class="button button-primary">
                <?php esc_html_e('Add a provider', 'cloud-vm-manager'); ?>
            </a>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped cvm-table">
                <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Provider', 'cloud-vm-manager'); ?></th>
                    <th scope="col"><?php esc_html_e('Status', 'cloud-vm-manager'); ?></th>
                    <th scope="col"><?php esc_html_e('Platform', 'cloud-vm-manager'); ?></th>
                    <th scope="col"><?php esc_html_e('Last synchronised', 'cloud-vm-manager'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($providers as $cvm_provider) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($cvm_provider->getName()); ?></strong></td>
                        <td>
                            <span class="cvm-badge cvm-status cvm-status-<?php echo esc_attr($cvm_provider->getStatus()); ?>">
                                <?php echo esc_html($cvm_provider->getStatus()); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($cvm_provider->getPlatformVersion() !== '' ? $cvm_provider->getPlatformVersion() : '—'); ?></td>
                        <td class="cvm-muted">
                            <?php
                            $cvm_synced = $cvm_provider->getString('last_synced_at');
                            echo esc_html($cvm_synced !== '' ? $cvm_synced : __('Never', 'cloud-vm-manager'));
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="cvm-card">
        <h2 class="cvm-card-title"><?php esc_html_e('Recent synchronisation', 'cloud-vm-manager'); ?></h2>
        <?php if ($runs === []) : ?>
            <p class="cvm-card-subtitle"><?php esc_html_e('No synchronisation has run yet.', 'cloud-vm-manager'); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped cvm-table">
                <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Resource', 'cloud-vm-manager'); ?></th>
                    <th scope="col"><?php esc_html_e('Status', 'cloud-vm-manager'); ?></th>
                    <th scope="col" class="cvm-col-count"><?php esc_html_e('Changes', 'cloud-vm-manager'); ?></th>
                    <th scope="col"><?php esc_html_e('Started', 'cloud-vm-manager'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($runs as $cvm_run) : ?>
                    <tr>
                        <td><?php echo esc_html($cvm_run->getResource()); ?></td>
                        <td>
                            <span class="cvm-badge cvm-status cvm-status-<?php echo esc_attr($cvm_run->getStatus()); ?>">
                                <?php echo esc_html($cvm_run->getStatus()); ?>
                            </span>
                        </td>
                        <td class="cvm-col-count"><?php echo esc_html((string) $cvm_run->getTotalChanges()); ?></td>
                        <td class="cvm-muted"><?php echo esc_html($cvm_run->getString('started_at')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="cvm-card">
        <h2 class="cvm-card-title"><?php esc_html_e('Latest log entries', 'cloud-vm-manager'); ?></h2>
        <?php if ($logs === []) : ?>
            <p class="cvm-card-subtitle"><?php esc_html_e('Nothing recorded yet.', 'cloud-vm-manager'); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped cvm-table">
                <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Level', 'cloud-vm-manager'); ?></th>
                    <th scope="col"><?php esc_html_e('Channel', 'cloud-vm-manager'); ?></th>
                    <th scope="col"><?php esc_html_e('Message', 'cloud-vm-manager'); ?></th>
                    <th scope="col"><?php esc_html_e('When', 'cloud-vm-manager'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $cvm_log) : ?>
                    <tr>
                        <td><?php echo esc_html($cvm_log->getLevel()); ?></td>
                        <td><?php echo esc_html($cvm_log->getChannel()); ?></td>
                        <td><?php echo esc_html($cvm_log->getMessage()); ?></td>
                        <td class="cvm-muted"><?php echo esc_html($cvm_log->getString('created_at')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
