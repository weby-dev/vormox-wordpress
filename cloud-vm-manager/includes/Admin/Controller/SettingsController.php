<?php

/**
 * Settings screen.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Admin\Controller;

use CloudVmManager\Admin\Access;
use CloudVmManager\Admin\Notices;
use CloudVmManager\Admin\SettingsFields;
use CloudVmManager\Admin\View;
use CloudVmManager\Cron\CronManager;
use CloudVmManager\Support\Settings;

defined('ABSPATH') || exit;

/**
 * Renders the settings screen and stores its submissions.
 *
 * Only declared keys are read from the request, and each value is sanitised by
 * the settings schema before it is written.
 */
final class SettingsController
{
    public const PAGE = 'cloud-vm-manager-settings';
    public const ACTION_SAVE = 'cvm_save_settings';

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var SettingsFields
     */
    private $fields;

    /**
     * @var CronManager
     */
    private $cron;

    /**
     * @var View
     */
    private $view;

    /**
     * @var Notices
     */
    private $notices;

    public function __construct(
        Settings $settings,
        SettingsFields $fields,
        CronManager $cron,
        View $view,
        Notices $notices
    ) {
        $this->settings = $settings;
        $this->fields = $fields;
        $this->cron = $cron;
        $this->view = $view;
        $this->notices = $notices;
    }

    public function render(): void
    {
        Access::assert();

        $this->view->render(
            'admin/settings',
            [
                'groups' => $this->fields->groups(),
                'values' => $this->settings->all(),
                'formAction' => admin_url('admin-post.php'),
                'nonceAction' => self::ACTION_SAVE,
                'saveAction' => self::ACTION_SAVE,
                'providersUrl' => ProvidersController::url(),
            ]
        );
    }

    public function handleSave(): void
    {
        Access::assert();
        check_admin_referer(self::ACTION_SAVE);

        $values = [];

        foreach ($this->fields->keys() as $key) {
            if (isset($_POST[$key])) {
                $values[$key] = sanitize_text_field(wp_unslash((string) $_POST[$key]));
            }
        }

        foreach ($this->fields->checkboxKeys() as $key) {
            $values[$key] = !empty($_POST[$key]);
        }

        $this->settings->update($values);

        /*
         * The maintenance job and, from the synchronisation phase on, the sync
         * job read their recurrence from the settings, so the schedule has to
         * follow a change immediately.
         */
        $this->cron->scheduleAll();

        $this->notices->success(__('Settings saved.', 'cloud-vm-manager'));

        wp_safe_redirect(self::url());

        exit;
    }

    /**
     * URL of the settings screen.
     *
     * @param array<string, string|int> $args
     */
    public static function url(array $args = []): string
    {
        return add_query_arg(
            array_merge(['page' => self::PAGE], $args),
            admin_url('admin.php')
        );
    }
}
