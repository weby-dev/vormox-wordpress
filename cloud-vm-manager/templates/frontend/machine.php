<?php

/**
 * Single machine view.
 *
 * @package CloudVmManager
 *
 * @var \CloudVmManager\Model\VmOrder    $machine         Machine being shown.
 * @var array<string, mixed>             $lock            Lock state.
 * @var bool                             $operable        Whether controls apply.
 * @var string                           $controls        Rendered control panel.
 * @var string                           $upgrade         Rendered upgrade panel.
 * @var array<string, mixed>             $metrics         Live metrics.
 * @var array<string, mixed>             $storage         File system usage.
 * @var string[]                         $timeframes      Metric timeframes.
 * @var array<string, mixed>             $details         Backend detail record.
 * @var array<int, array<string, mixed>> $activity        Backend activity log.
 * @var \CloudVmManager\Model\LogEntry[] $localActivity   Plugin activity log.
 * @var array<string, mixed>             $wallet          Wallet balance.
 * @var string                           $backUrl         URL of the machine list.
 * @var string                           $panelUrl        Link that opens the machine at the provider.
 * @var string                           $panelLabel      Label of that link.
 * @var int                              $refreshInterval Seconds between refreshes.
 */

defined('ABSPATH') || exit;

$cvm_created = $machine->getDateTime('created_at');
$cvm_renews = $machine->getDateTime('renews_at');
?>
<div class="cvm-dashboard cvm-machine-view"
     data-machine-id="<?php echo esc_attr((string) $machine->id()); ?>"
     data-cvm-building="<?php echo $machine->isBuilding() ? '1' : '0'; ?>"
     data-refresh-interval="<?php echo esc_attr((string) $refreshInterval); ?>">

    <header class="cvm-dashboard-header">
        <div>
            <a class="cvm-back" href="<?php echo esc_url($backUrl); ?>">
                <?php esc_html_e('← All machines', 'cloud-vm-manager'); ?>
            </a>
            <h2 class="cvm-dashboard-title">
                <?php
                echo esc_html(
                    $machine->getHostname() !== ''
                        ? $machine->getHostname()
                        : __('Machine being prepared', 'cloud-vm-manager')
                );
                ?>
            </h2>
        </div>
        <div class="cvm-header-actions">
            <?php if ($panelUrl !== '') : ?>
                <a class="cvm-button cvm-button-ghost" href="<?php echo esc_url($panelUrl); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html($panelLabel); ?>
                </a>
            <?php endif; ?>
            <span class="cvm-pill cvm-pill-<?php echo esc_attr($machine->getStatus()); ?>" data-cvm-live-status>
                <?php echo esc_html($machine->getStatus()); ?>
            </span>
        </div>
    </header>

    <?php if ($lock['locked']) : ?>
        <div class="cvm-notice cvm-notice-warning">
            <?php
            echo esc_html(
                $lock['message'] !== ''
                    ? $lock['message']
                    : __('This machine is locked. Controls are unavailable until it is released.', 'cloud-vm-manager')
            );
            ?>
        </div>
    <?php endif; ?>

    <?php if ($machine->isBuilding()) : ?>
        <div class="cvm-notice cvm-notice-info cvm-building">
            <span class="cvm-spinner" aria-hidden="true"></span>
            <?php esc_html_e('Your machine has been created and is starting up. This usually takes about a minute, and this page updates itself when it is ready.', 'cloud-vm-manager'); ?>
        </div>
    <?php elseif (!$machine->isProvisioned()) : ?>
        <div class="cvm-notice cvm-notice-info">
            <?php esc_html_e('This machine is still being deployed. Live data appears as soon as it is ready.', 'cloud-vm-manager'); ?>
        </div>
    <?php endif; ?>

    <div class="cvm-columns">
        <section class="cvm-panel">
            <h3><?php esc_html_e('Overview', 'cloud-vm-manager'); ?></h3>
            <dl class="cvm-specs cvm-specs-wide">
                <div>
                    <dt><?php esc_html_e('Address', 'cloud-vm-manager'); ?></dt>
                    <dd data-cvm-field="ip_address"><?php echo esc_html($machine->getIpAddress() !== '' ? $machine->getIpAddress() : '—'); ?></dd>
                </div>
                <div>
                    <dt><?php esc_html_e('Operating system', 'cloud-vm-manager'); ?></dt>
                    <dd><?php echo esc_html($machine->getOsName() !== '' ? $machine->getOsName() : '—'); ?></dd>
                </div>
                <div>
                    <dt><?php esc_html_e('CPU', 'cloud-vm-manager'); ?></dt>
                    <dd><?php echo esc_html($machine->getInt('cpu_cores') > 0 ? (string) $machine->getInt('cpu_cores') : '—'); ?></dd>
                </div>
                <div>
                    <dt><?php esc_html_e('Memory', 'cloud-vm-manager'); ?></dt>
                    <dd>
                        <?php
                        $cvm_ram = $machine->getInt('ram_mb');
                        echo esc_html($cvm_ram > 0 ? round($cvm_ram / 1024, 1) . ' GB' : '—');
                        ?>
                    </dd>
                </div>
                <div>
                    <dt><?php esc_html_e('Disk', 'cloud-vm-manager'); ?></dt>
                    <dd>
                        <?php
                        $cvm_disk = $machine->getInt('disk_gb');
                        echo esc_html($cvm_disk > 0 ? $cvm_disk . ' GB' : '—');
                        ?>
                    </dd>
                </div>
                <div>
                    <dt><?php esc_html_e('Bandwidth', 'cloud-vm-manager'); ?></dt>
                    <dd>
                        <?php
                        $cvm_bandwidth = $machine->getInt('bandwidth_gb');
                        echo esc_html($cvm_bandwidth > 0 ? $cvm_bandwidth . ' GB' : '—');
                        ?>
                    </dd>
                </div>
                <div>
                    <dt><?php esc_html_e('Created', 'cloud-vm-manager'); ?></dt>
                    <dd><?php echo esc_html($cvm_created !== null ? $cvm_created->format('Y-m-d') : '—'); ?></dd>
                </div>
                <div>
                    <dt><?php esc_html_e('Renews', 'cloud-vm-manager'); ?></dt>
                    <dd><?php echo esc_html($cvm_renews !== null ? $cvm_renews->format('Y-m-d') : '—'); ?></dd>
                </div>
                <div>
                    <dt><?php esc_html_e('Billing cycle', 'cloud-vm-manager'); ?></dt>
                    <dd><?php echo esc_html($machine->getBillingCycle()); ?></dd>
                </div>
                <div>
                    <dt><?php esc_html_e('Password', 'cloud-vm-manager'); ?></dt>
                    <dd>
                        <?php
                        echo esc_html(
                            $machine->isProvisioned()
                                ? __('Set — reset it from the controls', 'cloud-vm-manager')
                                : __('Not set yet', 'cloud-vm-manager')
                        );
                        ?>
                    </dd>
                </div>
            </dl>

            <?php if ($machine->getRemotePaymentId() !== '') : ?>
                <p>
                    <button type="button" class="cvm-button cvm-button-secondary" data-cvm-invoice>
                        <?php esc_html_e('Download invoice', 'cloud-vm-manager'); ?>
                    </button>
                </p>
            <?php endif; ?>
        </section>

        <section class="cvm-panel">
            <div class="cvm-panel-head">
                <h3><?php esc_html_e('Live metrics', 'cloud-vm-manager'); ?></h3>
                <select data-cvm-timeframe class="cvm-select">
                    <?php foreach ($timeframes as $cvm_timeframe) : ?>
                        <option value="<?php echo esc_attr($cvm_timeframe); ?>">
                            <?php echo esc_html(ucfirst($cvm_timeframe)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (!$metrics['available']) : ?>
                <p class="cvm-muted" data-cvm-metrics-message><?php echo esc_html((string) $metrics['message']); ?></p>
            <?php else : ?>
                <p class="cvm-muted" data-cvm-metrics-message></p>
            <?php endif; ?>

            <div class="cvm-gauges">
                <div class="cvm-gauge">
                    <span class="cvm-gauge-label"><?php esc_html_e('CPU', 'cloud-vm-manager'); ?></span>
                    <div class="cvm-progress">
                        <div class="cvm-progress-bar" data-cvm-gauge="cpu"
                             style="width: <?php echo esc_attr((string) $metrics['current']['cpu_percent']); ?>%"></div>
                    </div>
                    <span class="cvm-gauge-value" data-cvm-gauge-value="cpu">
                        <?php echo esc_html((string) $metrics['current']['cpu_percent']); ?>%
                    </span>
                </div>
                <div class="cvm-gauge">
                    <span class="cvm-gauge-label"><?php esc_html_e('Disk', 'cloud-vm-manager'); ?></span>
                    <div class="cvm-progress">
                        <div class="cvm-progress-bar" data-cvm-gauge="disk"
                             style="width: <?php echo esc_attr((string) $metrics['current']['disk_percent']); ?>%"></div>
                    </div>
                    <span class="cvm-gauge-value" data-cvm-gauge-value="disk">
                        <?php echo esc_html((string) $metrics['current']['disk_percent']); ?>%
                    </span>
                </div>
            </div>

            <figure class="cvm-chart">
                <figcaption><?php esc_html_e('CPU and memory usage', 'cloud-vm-manager'); ?></figcaption>
                <svg viewBox="0 0 600 180" preserveAspectRatio="none" role="img"
                     aria-label="<?php esc_attr_e('CPU and memory usage over time', 'cloud-vm-manager'); ?>"
                     data-cvm-chart="usage"></svg>
                <div class="cvm-legend">
                    <span class="cvm-legend-item cvm-legend-cpu"><?php esc_html_e('CPU', 'cloud-vm-manager'); ?></span>
                    <span class="cvm-legend-item cvm-legend-memory"><?php esc_html_e('Memory', 'cloud-vm-manager'); ?></span>
                </div>
            </figure>

            <figure class="cvm-chart">
                <figcaption><?php esc_html_e('Network traffic', 'cloud-vm-manager'); ?></figcaption>
                <svg viewBox="0 0 600 180" preserveAspectRatio="none" role="img"
                     aria-label="<?php esc_attr_e('Inbound and outbound traffic over time', 'cloud-vm-manager'); ?>"
                     data-cvm-chart="network"></svg>
                <div class="cvm-legend">
                    <span class="cvm-legend-item cvm-legend-in"><?php esc_html_e('Inbound', 'cloud-vm-manager'); ?></span>
                    <span class="cvm-legend-item cvm-legend-out"><?php esc_html_e('Outbound', 'cloud-vm-manager'); ?></span>
                </div>
            </figure>
        </section>
    </div>

    <?php
    /*
     * The control panel is rendered by the plugin from its own template, where
     * every value is escaped at the point it is printed.
     */
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo $controls;
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo $upgrade;
    ?>

    <div class="cvm-columns">
        <section class="cvm-panel">
            <h3><?php esc_html_e('Storage', 'cloud-vm-manager'); ?></h3>
            <div data-cvm-storage>
                <?php if (!$storage['available']) : ?>
                    <p class="cvm-notice cvm-notice-info"><?php echo esc_html((string) $storage['message']); ?></p>
                <?php elseif ($storage['drives'] === []) : ?>
                    <p class="cvm-muted"><?php esc_html_e('No file systems reported.', 'cloud-vm-manager'); ?></p>
                <?php else : ?>
                    <?php foreach ($storage['drives'] as $cvm_drive) : ?>
                        <div class="cvm-drive">
                            <div class="cvm-drive-head">
                                <strong><?php echo esc_html((string) $cvm_drive['mount_point']); ?></strong>
                                <span class="cvm-muted"><?php echo esc_html((string) $cvm_drive['fs_type']); ?></span>
                            </div>
                            <div class="cvm-progress">
                                <div class="cvm-progress-bar" style="width: <?php echo esc_attr((string) $cvm_drive['percent']); ?>%"></div>
                            </div>
                            <div class="cvm-drive-foot cvm-muted">
                                <?php
                                printf(
                                    /* translators: 1: used space, 2: total space. */
                                    esc_html__('%1$s used of %2$s', 'cloud-vm-manager'),
                                    esc_html((string) $cvm_drive['used']),
                                    esc_html((string) $cvm_drive['total'])
                                );
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="cvm-panel">
            <h3><?php esc_html_e('Activity', 'cloud-vm-manager'); ?></h3>
            <?php if ($activity === [] && $localActivity === []) : ?>
                <p class="cvm-muted"><?php esc_html_e('Nothing recorded yet.', 'cloud-vm-manager'); ?></p>
            <?php else : ?>
                <ul class="cvm-timeline">
                    <?php foreach ($activity as $cvm_entry) : ?>
                        <li>
                            <span class="cvm-timeline-time"><?php echo esc_html((string) $cvm_entry['timestamp']); ?></span>
                            <span class="cvm-timeline-message">
                                <?php echo esc_html((string) $cvm_entry['operation']); ?>
                                <em><?php echo esc_html((string) $cvm_entry['status']); ?></em>
                            </span>
                        </li>
                    <?php endforeach; ?>
                    <?php foreach ($localActivity as $cvm_entry) : ?>
                        <li>
                            <span class="cvm-timeline-time"><?php echo esc_html($cvm_entry->getString('created_at')); ?></span>
                            <span class="cvm-timeline-message"><?php echo esc_html($cvm_entry->getMessage()); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
