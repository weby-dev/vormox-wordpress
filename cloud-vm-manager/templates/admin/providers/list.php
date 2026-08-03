<?php

/**
 * Provider list screen.
 *
 * @package CloudVmManager
 *
 * @var \CloudVmManager\Model\Provider[] $providers    Providers to display.
 * @var string                           $addUrl       URL of the add form.
 * @var string                           $editUrlBase  Base URL of the edit form.
 * @var string                           $settingsUrl  URL of the settings screen.
 * @var string                           $postUrl      URL of admin-post.php.
 * @var string                           $deleteAction Action name of the delete handler.
 */

defined('ABSPATH') || exit;

$cvm_status_labels = [
    'connected' => __('Connected', 'cloud-vm-manager'),
    'disconnected' => __('Disconnected', 'cloud-vm-manager'),
    'error' => __('Error', 'cloud-vm-manager'),
];
?>
<div class="wrap cvm-wrap">
    <div class="cvm-page-header">
        <div>
            <h1 class="wp-heading-inline"><?php esc_html_e('Cloud Providers', 'cloud-vm-manager'); ?></h1>
            <p class="cvm-page-subtitle">
                <?php esc_html_e('Connect the backends that provision and bill your virtual machines.', 'cloud-vm-manager'); ?>
            </p>
        </div>
        <div class="cvm-page-actions">
            <a href="<?php echo esc_url($settingsUrl); ?>" class="button">
                <?php esc_html_e('Settings', 'cloud-vm-manager'); ?>
            </a>
            <a href="<?php echo esc_url($addUrl); ?>" class="button button-primary">
                <?php esc_html_e('Add provider', 'cloud-vm-manager'); ?>
            </a>
        </div>
    </div>

    <?php if ($providers === []) : ?>
        <div class="cvm-card cvm-empty-state">
            <h2><?php esc_html_e('No providers yet', 'cloud-vm-manager'); ?></h2>
            <p>
                <?php esc_html_e('Add your first cloud provider to synchronise its catalogue and start selling virtual machines.', 'cloud-vm-manager'); ?>
            </p>
            <a href="<?php echo esc_url($addUrl); ?>" class="button button-primary button-hero">
                <?php esc_html_e('Add your first provider', 'cloud-vm-manager'); ?>
            </a>
        </div>
    <?php else : ?>
        <div class="cvm-card">
            <table class="wp-list-table widefat fixed striped cvm-table">
                <thead>
                <tr>
                    <th scope="col" class="cvm-col-name"><?php esc_html_e('Provider', 'cloud-vm-manager'); ?></th>
                    <th scope="col"><?php esc_html_e('API endpoint', 'cloud-vm-manager'); ?></th>
                    <th scope="col"><?php esc_html_e('Region', 'cloud-vm-manager'); ?></th>
                    <th scope="col"><?php esc_html_e('Status', 'cloud-vm-manager'); ?></th>
                    <th scope="col"><?php esc_html_e('Platform', 'cloud-vm-manager'); ?></th>
                    <th scope="col" class="cvm-col-actions"><?php esc_html_e('Actions', 'cloud-vm-manager'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($providers as $cvm_provider) : ?>
                    <?php
                    $cvm_id = $cvm_provider->id();
                    $cvm_status = $cvm_provider->getStatus();
                    $cvm_edit_url = add_query_arg('provider', $cvm_id, $editUrlBase);
                    $cvm_delete_url = wp_nonce_url(
                        add_query_arg(
                            [
                                'action' => $deleteAction,
                                'provider' => $cvm_id,
                            ],
                            $postUrl
                        ),
                        $deleteAction . '_' . $cvm_id
                    );
                    ?>
                    <tr class="cvm-provider-row" data-provider-id="<?php echo esc_attr((string) $cvm_id); ?>">
                        <td class="cvm-col-name">
                            <strong>
                                <a href="<?php echo esc_url($cvm_edit_url); ?>">
                                    <?php echo esc_html($cvm_provider->getName()); ?>
                                </a>
                            </strong>
                            <?php if ($cvm_provider->isDefault()) : ?>
                                <span class="cvm-badge cvm-badge-default"><?php esc_html_e('Default', 'cloud-vm-manager'); ?></span>
                            <?php endif; ?>
                            <?php if (!$cvm_provider->isActive()) : ?>
                                <span class="cvm-badge cvm-badge-muted"><?php esc_html_e('Disabled', 'cloud-vm-manager'); ?></span>
                            <?php endif; ?>
                            <div class="cvm-muted"><?php echo esc_html($cvm_provider->getEmail()); ?></div>
                        </td>
                        <td>
                            <code class="cvm-code"><?php echo esc_html($cvm_provider->getApiUrl()); ?></code>
                            <?php if (!$cvm_provider->verifySsl()) : ?>
                                <div class="cvm-muted cvm-warning-text">
                                    <?php esc_html_e('TLS verification disabled', 'cloud-vm-manager'); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($cvm_provider->getRegion() !== '' ? $cvm_provider->getRegion() : '—'); ?></td>
                        <td>
                            <span class="cvm-badge cvm-status cvm-status-<?php echo esc_attr($cvm_status); ?>">
                                <?php echo esc_html($cvm_status_labels[$cvm_status] ?? $cvm_status); ?>
                            </span>
                            <?php if ($cvm_provider->getLastError() !== '') : ?>
                                <div class="cvm-muted cvm-error-text"><?php echo esc_html($cvm_provider->getLastError()); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            echo esc_html(
                                $cvm_provider->getPlatformVersion() !== ''
                                    ? $cvm_provider->getPlatformVersion()
                                    : '—'
                            );
                            ?>
                        </td>
                        <td class="cvm-col-actions">
                            <div class="cvm-actions">
                                <button type="button" class="button cvm-action" data-action="cvm_test_connection">
                                    <?php esc_html_e('Test', 'cloud-vm-manager'); ?>
                                </button>
                                <?php if ($cvm_provider->isConnected()) : ?>
                                    <button type="button" class="button cvm-action" data-action="cvm_sync_provider">
                                        <?php esc_html_e('Sync', 'cloud-vm-manager'); ?>
                                    </button>
                                    <button type="button" class="button cvm-action" data-action="cvm_refresh_token">
                                        <?php esc_html_e('Refresh token', 'cloud-vm-manager'); ?>
                                    </button>
                                    <button type="button" class="button cvm-action" data-action="cvm_disconnect_provider" data-confirm="1">
                                        <?php esc_html_e('Disconnect', 'cloud-vm-manager'); ?>
                                    </button>
                                <?php else : ?>
                                    <button type="button" class="button cvm-action" data-action="cvm_connect_provider">
                                        <?php esc_html_e('Connect', 'cloud-vm-manager'); ?>
                                    </button>
                                <?php endif; ?>
                                <a href="<?php echo esc_url($cvm_edit_url); ?>" class="button">
                                    <?php esc_html_e('Edit', 'cloud-vm-manager'); ?>
                                </a>
                                <a
                                    href="<?php echo esc_url($cvm_delete_url); ?>"
                                    class="button button-link-delete cvm-delete"
                                    data-confirm="<?php esc_attr_e('Delete this provider? Synchronised catalogue data stays until it is removed manually.', 'cloud-vm-manager'); ?>"
                                >
                                    <?php esc_html_e('Delete', 'cloud-vm-manager'); ?>
                                </a>
                            </div>
                            <div class="cvm-action-feedback" role="status" aria-live="polite"></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
