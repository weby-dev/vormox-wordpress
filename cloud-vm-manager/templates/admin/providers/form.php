<?php

/**
 * Provider add and edit form.
 *
 * @package CloudVmManager
 *
 * @var \CloudVmManager\Model\Provider|null $provider    Provider being edited, null when adding.
 * @var string                              $backUrl     URL of the provider list.
 * @var string                              $formAction  URL the form posts to.
 * @var string                              $nonceAction Nonce action of the form.
 * @var string                              $saveAction  Action name of the save handler.
 */

defined('ABSPATH') || exit;

$cvm_is_edit = $provider !== null;
$cvm_name = $cvm_is_edit ? $provider->getName() : '';
$cvm_api_url = $cvm_is_edit ? $provider->getApiUrl() : '';
$cvm_host_url = $cvm_is_edit ? $provider->getHostUrl() : '';
$cvm_email = $cvm_is_edit ? $provider->getEmail() : '';
$cvm_region = $cvm_is_edit ? $provider->getRegion() : '';
$cvm_currency = $cvm_is_edit ? $provider->getCurrency() : '';
$cvm_description = $cvm_is_edit ? $provider->getDescription() : '';
$cvm_timeout = $cvm_is_edit ? $provider->getTimeout() : 30;
$cvm_sort_order = $cvm_is_edit ? $provider->getInt('sort_order') : 0;
$cvm_verify_ssl = $cvm_is_edit ? $provider->verifySsl() : true;
$cvm_is_active = $cvm_is_edit ? $provider->isActive() : true;
$cvm_is_default = $cvm_is_edit ? $provider->isDefault() : false;
?>
<div class="wrap cvm-wrap">
    <div class="cvm-page-header">
        <div>
            <h1 class="wp-heading-inline">
                <?php
                echo $cvm_is_edit
                    ? esc_html__('Edit provider', 'cloud-vm-manager')
                    : esc_html__('Add provider', 'cloud-vm-manager');
                ?>
            </h1>
            <p class="cvm-page-subtitle">
                <?php esc_html_e('Credentials are encrypted before they are stored and are never shown again.', 'cloud-vm-manager'); ?>
            </p>
        </div>
        <div class="cvm-page-actions">
            <a href="<?php echo esc_url($backUrl); ?>" class="button">
                <?php esc_html_e('Back to providers', 'cloud-vm-manager'); ?>
            </a>
        </div>
    </div>

    <form method="post" action="<?php echo esc_url($formAction); ?>" class="cvm-form">
        <?php wp_nonce_field($nonceAction); ?>
        <input type="hidden" name="action" value="<?php echo esc_attr($saveAction); ?>">
        <?php if ($cvm_is_edit) : ?>
            <input type="hidden" name="provider_id" value="<?php echo esc_attr((string) $provider->id()); ?>">
        <?php endif; ?>

        <div class="cvm-card">
            <h2 class="cvm-card-title"><?php esc_html_e('Identity', 'cloud-vm-manager'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="cvm-name"><?php esc_html_e('Provider name', 'cloud-vm-manager'); ?></label></th>
                    <td>
                        <input name="name" id="cvm-name" type="text" class="regular-text" required
                               value="<?php echo esc_attr($cvm_name); ?>">
                        <p class="description"><?php esc_html_e('Shown to administrators and used to label products.', 'cloud-vm-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="cvm-region"><?php esc_html_e('Region', 'cloud-vm-manager'); ?></label></th>
                    <td>
                        <input name="region" id="cvm-region" type="text" class="regular-text"
                               value="<?php echo esc_attr($cvm_region); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="cvm-currency"><?php esc_html_e('Currency', 'cloud-vm-manager'); ?></label></th>
                    <td>
                        <input name="currency" id="cvm-currency" type="text" class="small-text" maxlength="10"
                               value="<?php echo esc_attr($cvm_currency); ?>">
                        <p class="description"><?php esc_html_e('Currency the provider bills in, for example INR.', 'cloud-vm-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="cvm-description"><?php esc_html_e('Description', 'cloud-vm-manager'); ?></label></th>
                    <td>
                        <textarea name="description" id="cvm-description" rows="3" class="large-text"><?php
                            echo esc_textarea($cvm_description);
                        ?></textarea>
                    </td>
                </tr>
            </table>
        </div>

        <div class="cvm-card">
            <h2 class="cvm-card-title"><?php esc_html_e('Connection', 'cloud-vm-manager'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="cvm-api-url"><?php esc_html_e('API URL', 'cloud-vm-manager'); ?></label></th>
                    <td>
                        <input name="api_url" id="cvm-api-url" type="url" class="regular-text code" required
                               placeholder="https://api.example.com"
                               value="<?php echo esc_attr($cvm_api_url); ?>">
                        <p class="description"><?php esc_html_e('Base URL of the REST API, without a trailing slash.', 'cloud-vm-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="cvm-host-url"><?php esc_html_e('Panel URL', 'cloud-vm-manager'); ?></label></th>
                    <td>
                        <input name="host_url" id="cvm-host-url" type="url" class="regular-text code"
                               placeholder="https://panel.example.com"
                               value="<?php echo esc_attr($cvm_host_url); ?>">
                        <p class="description"><?php esc_html_e('Optional. Used for links back to the provider panel.', 'cloud-vm-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="cvm-timeout"><?php esc_html_e('Timeout', 'cloud-vm-manager'); ?></label></th>
                    <td>
                        <input name="timeout" id="cvm-timeout" type="number" class="small-text" min="5" max="300"
                               value="<?php echo esc_attr((string) $cvm_timeout); ?>">
                        <span class="cvm-suffix"><?php esc_html_e('seconds', 'cloud-vm-manager'); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('TLS verification', 'cloud-vm-manager'); ?></th>
                    <td>
                        <label for="cvm-verify-ssl">
                            <input name="verify_ssl" id="cvm-verify-ssl" type="checkbox" value="1"
                                <?php checked($cvm_verify_ssl); ?>>
                            <?php esc_html_e('Verify the TLS certificate of the API', 'cloud-vm-manager'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('Only disable this while testing against a self signed certificate.', 'cloud-vm-manager'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="cvm-card">
            <h2 class="cvm-card-title"><?php esc_html_e('Credentials', 'cloud-vm-manager'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="cvm-email"><?php esc_html_e('Account email', 'cloud-vm-manager'); ?></label></th>
                    <td>
                        <input name="email" id="cvm-email" type="email" class="regular-text" required
                               autocomplete="off" value="<?php echo esc_attr($cvm_email); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="cvm-password"><?php esc_html_e('Account password', 'cloud-vm-manager'); ?></label></th>
                    <td>
                        <input name="password" id="cvm-password" type="password" class="regular-text"
                               autocomplete="new-password"
                            <?php echo $cvm_is_edit ? '' : 'required'; ?>>
                        <p class="description">
                            <?php
                            echo $cvm_is_edit
                                ? esc_html__('Leave blank to keep the stored password.', 'cloud-vm-manager')
                                : esc_html__('Used to obtain a bearer token. Stored encrypted.', 'cloud-vm-manager');
                            ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="cvm-api-token"><?php esc_html_e('API token', 'cloud-vm-manager'); ?></label></th>
                    <td>
                        <input name="api_token" id="cvm-api-token" type="password" class="regular-text"
                               autocomplete="off">
                        <p class="description">
                            <?php esc_html_e('Optional. Provide a token to skip the login call. Leave blank to obtain one automatically.', 'cloud-vm-manager'); ?>
                        </p>
                        <?php if ($cvm_is_edit && $provider->hasToken()) : ?>
                            <p class="description cvm-muted">
                                <?php esc_html_e('A token is currently stored for this provider.', 'cloud-vm-manager'); ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="cvm-card">
            <h2 class="cvm-card-title"><?php esc_html_e('Availability', 'cloud-vm-manager'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Status', 'cloud-vm-manager'); ?></th>
                    <td>
                        <label for="cvm-is-active">
                            <input name="is_active" id="cvm-is-active" type="checkbox" value="1"
                                <?php checked($cvm_is_active); ?>>
                            <?php esc_html_e('Provider is available for new orders', 'cloud-vm-manager'); ?>
                        </label>
                        <br>
                        <label for="cvm-is-default">
                            <input name="is_default" id="cvm-is-default" type="checkbox" value="1"
                                <?php checked($cvm_is_default); ?>>
                            <?php esc_html_e('Use as the default provider', 'cloud-vm-manager'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="cvm-sort-order"><?php esc_html_e('Sort order', 'cloud-vm-manager'); ?></label></th>
                    <td>
                        <input name="sort_order" id="cvm-sort-order" type="number" class="small-text" min="0"
                               value="<?php echo esc_attr((string) $cvm_sort_order); ?>">
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary button-large">
                <?php
                echo $cvm_is_edit
                    ? esc_html__('Save provider', 'cloud-vm-manager')
                    : esc_html__('Add provider', 'cloud-vm-manager');
                ?>
            </button>
            <a href="<?php echo esc_url($backUrl); ?>" class="button button-large"><?php esc_html_e('Cancel', 'cloud-vm-manager'); ?></a>
        </p>
    </form>
</div>
