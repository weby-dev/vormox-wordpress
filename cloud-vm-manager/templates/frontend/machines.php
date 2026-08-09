<?php

/**
 * Customer machine list.
 *
 * @package CloudVmManager
 *
 * @var \CloudVmManager\Model\VmOrder[]  $machines     Machines of the customer.
 * @var int                              $total        Total machines.
 * @var int                              $page         Current page.
 * @var int                              $pages        Number of pages.
 * @var array<string, int|float>         $summary      Summary counters.
 * @var array<string, mixed>|null        $wallet       Wallet balance, when available.
 * @var array<int, array<string, mixed>> $transactions Recent wallet movements.
 * @var \CloudVmManager\Model\LogEntry[] $activity     Recent account activity.
 * @var string                           $baseUrl      URL of the dashboard.
 */

defined('ABSPATH') || exit;
?>
<div class="cvm-dashboard">
    <header class="cvm-dashboard-header">
        <h2 class="cvm-dashboard-title"><?php esc_html_e('Your virtual machines', 'cloud-vm-manager'); ?></h2>
    </header>

    <div class="cvm-stats">
        <div class="cvm-stat">
            <span class="cvm-stat-label"><?php esc_html_e('Machines', 'cloud-vm-manager'); ?></span>
            <span class="cvm-stat-value"><?php echo esc_html((string) $summary['total']); ?></span>
        </div>
        <div class="cvm-stat">
            <span class="cvm-stat-label"><?php esc_html_e('Running', 'cloud-vm-manager'); ?></span>
            <span class="cvm-stat-value"><?php echo esc_html((string) $summary['active']); ?></span>
        </div>
        <div class="cvm-stat">
            <span class="cvm-stat-label"><?php esc_html_e('Total spent', 'cloud-vm-manager'); ?></span>
            <span class="cvm-stat-value"><?php echo esc_html(number_format((float) $summary['spent'], 2)); ?></span>
        </div>
        <?php if (is_array($wallet) && $wallet['available']) : ?>
            <div class="cvm-stat">
                <span class="cvm-stat-label"><?php esc_html_e('Wallet', 'cloud-vm-manager'); ?></span>
                <span class="cvm-stat-value">
                    <?php echo esc_html($wallet['currency'] . ' ' . number_format((float) $wallet['balance'], 2)); ?>
                </span>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($machines === []) : ?>
        <div class="cvm-panel cvm-empty">
            <h3><?php esc_html_e('No machines yet', 'cloud-vm-manager'); ?></h3>
            <p><?php esc_html_e('Machines you order appear here as soon as they are deployed.', 'cloud-vm-manager'); ?></p>
        </div>
    <?php else : ?>
        <div class="cvm-machine-grid">
            <?php foreach ($machines as $cvm_machine) : ?>
                <?php $cvm_link = add_query_arg('cvm_machine', $cvm_machine->id(), $baseUrl); ?>
                <article class="cvm-machine-card"
                         data-machine-id="<?php echo esc_attr((string) $cvm_machine->id()); ?>"
                         data-cvm-building="<?php echo $cvm_machine->isBuilding() ? '1' : '0'; ?>">
                    <header class="cvm-machine-card-header">
                        <h3>
                            <a href="<?php echo esc_url($cvm_link); ?>">
                                <?php
                                echo esc_html(
                                    $cvm_machine->getHostname() !== ''
                                        ? $cvm_machine->getHostname()
                                        : __('Machine being prepared', 'cloud-vm-manager')
                                );
                                ?>
                            </a>
                        </h3>
                        <span class="cvm-pill cvm-pill-<?php echo esc_attr($cvm_machine->getStatus()); ?>"
                              data-cvm-live-status>
                            <?php echo esc_html($cvm_machine->getStatus()); ?>
                        </span>
                    </header>

                    <dl class="cvm-specs">
                        <div>
                            <dt><?php esc_html_e('Address', 'cloud-vm-manager'); ?></dt>
                            <dd><?php echo esc_html($cvm_machine->getIpAddress() !== '' ? $cvm_machine->getIpAddress() : '—'); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('Operating system', 'cloud-vm-manager'); ?></dt>
                            <dd><?php echo esc_html($cvm_machine->getOsName() !== '' ? $cvm_machine->getOsName() : '—'); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('Billing', 'cloud-vm-manager'); ?></dt>
                            <dd><?php echo esc_html($cvm_machine->getBillingCycle()); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('Renews', 'cloud-vm-manager'); ?></dt>
                            <dd>
                                <?php
                                $cvm_renews = $cvm_machine->getDateTime('renews_at');
                                echo esc_html($cvm_renews !== null ? $cvm_renews->format('Y-m-d') : '—');
                                ?>
                            </dd>
                        </div>
                    </dl>

                    <footer class="cvm-machine-card-footer">
                        <a class="cvm-button" href="<?php echo esc_url($cvm_link); ?>">
                            <?php esc_html_e('Manage', 'cloud-vm-manager'); ?>
                        </a>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1) : ?>
            <nav class="cvm-pagination">
                <?php for ($cvm_i = 1; $cvm_i <= $pages; ++$cvm_i) : ?>
                    <a
                        class="cvm-page<?php echo $cvm_i === $page ? ' is-current' : ''; ?>"
                        href="<?php echo esc_url(add_query_arg('cvm_page', $cvm_i, $baseUrl)); ?>"
                    >
                        <?php echo esc_html((string) $cvm_i); ?>
                    </a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

    <div class="cvm-columns">
        <section class="cvm-panel">
            <h3><?php esc_html_e('Recent activity', 'cloud-vm-manager'); ?></h3>
            <?php if ($activity === []) : ?>
                <p class="cvm-muted"><?php esc_html_e('Nothing recorded yet.', 'cloud-vm-manager'); ?></p>
            <?php else : ?>
                <ul class="cvm-timeline">
                    <?php foreach ($activity as $cvm_entry) : ?>
                        <li>
                            <span class="cvm-timeline-time"><?php echo esc_html($cvm_entry->getString('created_at')); ?></span>
                            <span class="cvm-timeline-message"><?php echo esc_html($cvm_entry->getMessage()); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="cvm-panel">
            <h3><?php esc_html_e('Wallet movements', 'cloud-vm-manager'); ?></h3>
            <?php if ($transactions === []) : ?>
                <p class="cvm-muted"><?php esc_html_e('No movements recorded.', 'cloud-vm-manager'); ?></p>
            <?php else : ?>
                <ul class="cvm-timeline">
                    <?php foreach ($transactions as $cvm_transaction) : ?>
                        <li>
                            <span class="cvm-timeline-time"><?php echo esc_html((string) $cvm_transaction['timestamp']); ?></span>
                            <span class="cvm-timeline-message"><?php echo esc_html((string) $cvm_transaction['description']); ?></span>
                            <span class="cvm-timeline-amount<?php echo (float) $cvm_transaction['amount'] < 0 ? ' is-debit' : ' is-credit'; ?>">
                                <?php echo esc_html(number_format((float) $cvm_transaction['amount'], 2)); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
