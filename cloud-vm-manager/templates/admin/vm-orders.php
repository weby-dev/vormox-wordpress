<?php

/**
 * VM orders screen.
 *
 * @package CloudVmManager
 *
 * @var \CloudVmManager\Model\VmOrder[] $orders        Machines to display.
 * @var int                             $total         Total number of machines.
 * @var int                             $page          Current page.
 * @var int                             $pages         Number of pages.
 * @var string                          $status        Active status filter.
 * @var array<string, string>           $statuses      Status labels.
 * @var array<string, int>              $counts        Machine count per status.
 * @var array<int, string>              $providerNames Provider names by identifier.
 * @var string                          $baseUrl       URL of the screen.
 */

defined('ABSPATH') || exit;
?>
<div class="wrap cvm-wrap">
    <div class="cvm-page-header">
        <div>
            <h1 class="wp-heading-inline"><?php esc_html_e('Virtual Machines', 'cloud-vm-manager'); ?></h1>
            <p class="cvm-page-subtitle">
                <?php
                printf(
                    /* translators: %d: number of machines. */
                    esc_html__('%d machine(s) sold through this store.', 'cloud-vm-manager'),
                    (int) $total
                );
                ?>
            </p>
        </div>
    </div>

    <ul class="subsubsub">
        <li>
            <a href="<?php echo esc_url($baseUrl); ?>" class="<?php echo $status === '' ? 'current' : ''; ?>">
                <?php esc_html_e('All', 'cloud-vm-manager'); ?>
            </a>
        </li>
        <?php foreach ($statuses as $cvm_value => $cvm_label) : ?>
            <li>
                |
                <a
                    href="<?php echo esc_url(add_query_arg('status', $cvm_value, $baseUrl)); ?>"
                    class="<?php echo $status === $cvm_value ? 'current' : ''; ?>"
                >
                    <?php echo esc_html($cvm_label); ?>
                    <span class="count">(<?php echo esc_html((string) ($counts[$cvm_value] ?? 0)); ?>)</span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="cvm-card">
        <?php if ($orders === []) : ?>
            <p class="cvm-card-subtitle"><?php esc_html_e('No machines match this filter yet.', 'cloud-vm-manager'); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped cvm-table">
                <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Machine', 'cloud-vm-manager'); ?></th>
                    <th scope="col"><?php esc_html_e('Order', 'cloud-vm-manager'); ?></th>
                    <th scope="col"><?php esc_html_e('Provider', 'cloud-vm-manager'); ?></th>
                    <th scope="col"><?php esc_html_e('Status', 'cloud-vm-manager'); ?></th>
                    <th scope="col"><?php esc_html_e('Backend IDs', 'cloud-vm-manager'); ?></th>
                    <th scope="col"><?php esc_html_e('Billing', 'cloud-vm-manager'); ?></th>
                    <th scope="col"><?php esc_html_e('Created', 'cloud-vm-manager'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $cvm_order) : ?>
                    <tr>
                        <td>
                            <strong>
                                <?php
                                echo esc_html(
                                    $cvm_order->getHostname() !== ''
                                        ? $cvm_order->getHostname()
                                        : __('Not named yet', 'cloud-vm-manager')
                                );
                                ?>
                            </strong>
                            <?php if ($cvm_order->getIpAddress() !== '') : ?>
                                <div class="cvm-muted"><?php echo esc_html($cvm_order->getIpAddress()); ?></div>
                            <?php endif; ?>
                            <?php if ($cvm_order->getOsName() !== '') : ?>
                                <div class="cvm-muted"><?php echo esc_html($cvm_order->getOsName()); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $cvm_order_link = get_edit_post_link($cvm_order->getWcOrderId()); ?>
                            <?php if (is_string($cvm_order_link) && $cvm_order_link !== '') : ?>
                                <a href="<?php echo esc_url($cvm_order_link); ?>">
                                    #<?php echo esc_html((string) $cvm_order->getWcOrderId()); ?>
                                </a>
                            <?php else : ?>
                                #<?php echo esc_html((string) $cvm_order->getWcOrderId()); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($providerNames[$cvm_order->getProviderId()] ?? '—'); ?></td>
                        <td>
                            <span class="cvm-badge cvm-status cvm-status-<?php echo esc_attr($cvm_order->getStatus()); ?>">
                                <?php echo esc_html($statuses[$cvm_order->getStatus()] ?? $cvm_order->getStatus()); ?>
                            </span>
                            <div class="cvm-muted"><?php echo esc_html($cvm_order->getProvisioningStatus()); ?></div>
                            <?php if ($cvm_order->getErrorMessage() !== '') : ?>
                                <div class="cvm-muted cvm-error-text"><?php echo esc_html($cvm_order->getErrorMessage()); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="cvm-muted">
                            <?php if ($cvm_order->getRemoteVmId() > 0) : ?>
                                <div>vm: <?php echo esc_html((string) $cvm_order->getRemoteVmId()); ?></div>
                            <?php endif; ?>
                            <?php if ($cvm_order->getRemoteOrderId() !== '') : ?>
                                <div>order: <?php echo esc_html($cvm_order->getRemoteOrderId()); ?></div>
                            <?php endif; ?>
                            <?php if ($cvm_order->getRemotePaymentId() !== '') : ?>
                                <div>payment: <?php echo esc_html($cvm_order->getRemotePaymentId()); ?></div>
                            <?php endif; ?>
                            <?php if ($cvm_order->getGroupId() !== '') : ?>
                                <div>group: <?php echo esc_html($cvm_order->getGroupId()); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html($cvm_order->getBillingCycle()); ?>
                            <div class="cvm-muted">
                                <?php echo esc_html($cvm_order->getCurrency() . ' ' . number_format($cvm_order->getSaleAmount(), 2)); ?>
                            </div>
                        </td>
                        <td class="cvm-muted"><?php echo esc_html($cvm_order->getString('created_at')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($pages > 1) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        echo wp_kses_post(
                            paginate_links(
                                [
                                    'base' => add_query_arg('paged', '%#%', $baseUrl),
                                    'format' => '',
                                    'current' => $page,
                                    'total' => $pages,
                                ]
                            ) ?? ''
                        );
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
