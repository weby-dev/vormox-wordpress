<?php

/**
 * Settings screen.
 *
 * @package CloudVmManager
 *
 * @var array<int, array<string, mixed>> $groups       Field groups to render.
 * @var array<string, mixed>             $values       Current setting values.
 * @var string                           $formAction   URL the form posts to.
 * @var string                           $nonceAction  Nonce action of the form.
 * @var string                           $saveAction   Action name of the save handler.
 * @var string                           $providersUrl URL of the providers screen.
 */

defined('ABSPATH') || exit;
?>
<div class="wrap cvm-wrap">
    <div class="cvm-page-header">
        <div>
            <h1 class="wp-heading-inline"><?php esc_html_e('Cloud VM Settings', 'cloud-vm-manager'); ?></h1>
            <p class="cvm-page-subtitle">
                <?php esc_html_e('Defaults applied to every provider, synchronisation run and customer action.', 'cloud-vm-manager'); ?>
            </p>
        </div>
        <div class="cvm-page-actions">
            <a href="<?php echo esc_url($providersUrl); ?>" class="button">
                <?php esc_html_e('Providers', 'cloud-vm-manager'); ?>
            </a>
        </div>
    </div>

    <form method="post" action="<?php echo esc_url($formAction); ?>" class="cvm-form">
        <?php wp_nonce_field($nonceAction); ?>
        <input type="hidden" name="action" value="<?php echo esc_attr($saveAction); ?>">

        <?php foreach ($groups as $cvm_group) : ?>
            <div class="cvm-card">
                <h2 class="cvm-card-title"><?php echo esc_html((string) $cvm_group['title']); ?></h2>
                <?php if (($cvm_group['description'] ?? '') !== '') : ?>
                    <p class="cvm-card-subtitle"><?php echo esc_html((string) $cvm_group['description']); ?></p>
                <?php endif; ?>

                <table class="form-table" role="presentation">
                    <?php foreach ($cvm_group['fields'] as $cvm_field) : ?>
                        <?php
                        $cvm_key = (string) $cvm_field['key'];
                        $cvm_type = (string) ($cvm_field['type'] ?? 'text');
                        $cvm_value = $values[$cvm_key] ?? '';
                        $cvm_id = 'cvm-setting-' . str_replace('_', '-', $cvm_key);
                        ?>
                        <tr>
                            <th scope="row">
                                <label for="<?php echo esc_attr($cvm_id); ?>">
                                    <?php echo esc_html((string) $cvm_field['label']); ?>
                                </label>
                            </th>
                            <td>
                                <?php if ($cvm_type === 'checkbox') : ?>
                                    <label for="<?php echo esc_attr($cvm_id); ?>">
                                        <input type="checkbox" name="<?php echo esc_attr($cvm_key); ?>"
                                               id="<?php echo esc_attr($cvm_id); ?>" value="1"
                                            <?php checked((bool) $cvm_value); ?>>
                                        <?php echo esc_html((string) ($cvm_field['checkbox_label'] ?? '')); ?>
                                    </label>
                                <?php elseif ($cvm_type === 'select') : ?>
                                    <select name="<?php echo esc_attr($cvm_key); ?>" id="<?php echo esc_attr($cvm_id); ?>">
                                        <?php foreach ((array) ($cvm_field['options'] ?? []) as $cvm_option => $cvm_label) : ?>
                                            <option value="<?php echo esc_attr((string) $cvm_option); ?>"
                                                <?php selected((string) $cvm_value, (string) $cvm_option); ?>>
                                                <?php echo esc_html((string) $cvm_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($cvm_type === 'number') : ?>
                                    <input type="number" name="<?php echo esc_attr($cvm_key); ?>"
                                           id="<?php echo esc_attr($cvm_id); ?>" class="small-text"
                                           value="<?php echo esc_attr((string) $cvm_value); ?>"
                                           min="<?php echo esc_attr((string) ($cvm_field['min'] ?? 0)); ?>"
                                           max="<?php echo esc_attr((string) ($cvm_field['max'] ?? 100000)); ?>"
                                           step="<?php echo esc_attr((string) ($cvm_field['step'] ?? '1')); ?>">
                                    <?php if (($cvm_field['suffix'] ?? '') !== '') : ?>
                                        <span class="cvm-suffix"><?php echo esc_html((string) $cvm_field['suffix']); ?></span>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <input type="text" name="<?php echo esc_attr($cvm_key); ?>"
                                           id="<?php echo esc_attr($cvm_id); ?>" class="regular-text"
                                           value="<?php echo esc_attr((string) $cvm_value); ?>">
                                <?php endif; ?>

                                <?php if (($cvm_field['description'] ?? '') !== '') : ?>
                                    <p class="description"><?php echo esc_html((string) $cvm_field['description']); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endforeach; ?>

        <p class="submit">
            <button type="submit" class="button button-primary button-large">
                <?php esc_html_e('Save settings', 'cloud-vm-manager'); ?>
            </button>
        </p>
    </form>
</div>
