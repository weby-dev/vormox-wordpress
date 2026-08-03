<?php

/**
 * Providers screen.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Admin\Controller;

use CloudVmManager\Admin\Access;
use CloudVmManager\Admin\Notices;
use CloudVmManager\Admin\View;
use CloudVmManager\Exception\ValidationException;
use CloudVmManager\Model\Provider;
use CloudVmManager\Service\Provider\ProviderService;

defined('ABSPATH') || exit;

/**
 * Renders the provider list and form, and handles their submissions.
 *
 * Reads are guarded by a capability check, writes additionally by a nonce, and
 * every write ends in a redirect so a refresh cannot repeat the action.
 */
final class ProvidersController
{
    public const PAGE = 'cloud-vm-manager';
    public const ACTION_SAVE = 'cvm_save_provider';
    public const ACTION_DELETE = 'cvm_delete_provider';

    /**
     * @var ProviderService
     */
    private $providers;

    /**
     * @var View
     */
    private $view;

    /**
     * @var Notices
     */
    private $notices;

    public function __construct(ProviderService $providers, View $view, Notices $notices)
    {
        $this->providers = $providers;
        $this->view = $view;
        $this->notices = $notices;
    }

    /**
     * Render the list, or the form when one is requested.
     */
    public function render(): void
    {
        Access::assert();

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash((string) $_GET['action'])) : '';

        if ($action === 'new') {
            $this->renderForm(null);

            return;
        }

        if ($action === 'edit') {
            $providerId = isset($_GET['provider']) ? absint(wp_unslash((string) $_GET['provider'])) : 0;
            $provider = $this->providers->find($providerId);

            if ($provider === null) {
                $this->notices->error(__('That provider no longer exists.', 'cloud-vm-manager'));
                $this->renderList();

                return;
            }

            $this->renderForm($provider);

            return;
        }

        $this->renderList();
    }

    /**
     * Persist a submitted provider.
     */
    public function handleSave(): void
    {
        Access::assert();
        check_admin_referer(self::ACTION_SAVE);

        $providerId = isset($_POST['provider_id']) ? absint(wp_unslash((string) $_POST['provider_id'])) : 0;
        $input = $this->readInput();

        try {
            if ($providerId > 0) {
                $provider = $this->providers->update($providerId, $input);
                $this->notices->success(
                    sprintf(
                        /* translators: %s: provider name. */
                        __('Provider "%s" was updated.', 'cloud-vm-manager'),
                        $provider->getName()
                    )
                );
            } else {
                $provider = $this->providers->create($input);
                $this->notices->success(
                    sprintf(
                        /* translators: %s: provider name. */
                        __('Provider "%s" was added. Test the connection to authenticate.', 'cloud-vm-manager'),
                        $provider->getName()
                    )
                );
            }
        } catch (ValidationException $exception) {
            $this->notices->error($exception->getMessage());

            foreach ($exception->errors() as $error) {
                $this->notices->error($error);
            }

            $this->redirect($providerId > 0 ? ['action' => 'edit', 'provider' => $providerId] : ['action' => 'new']);

            return;
        }

        $this->redirect();
    }

    /**
     * Delete a provider.
     */
    public function handleDelete(): void
    {
        Access::assert();

        $providerId = isset($_REQUEST['provider']) ? absint(wp_unslash((string) $_REQUEST['provider'])) : 0;

        check_admin_referer(self::ACTION_DELETE . '_' . $providerId);

        if ($this->providers->delete($providerId)) {
            $this->notices->success(__('The provider was deleted.', 'cloud-vm-manager'));
        } else {
            $this->notices->error(__('That provider could not be deleted.', 'cloud-vm-manager'));
        }

        $this->redirect();
    }

    /**
     * URL of the providers screen.
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

    private function renderList(): void
    {
        $this->view->render(
            'admin/providers/list',
            [
                'providers' => $this->providers->all(),
                'addUrl' => self::url(['action' => 'new']),
                'editUrlBase' => self::url(['action' => 'edit']),
                'settingsUrl' => SettingsController::url(),
                'postUrl' => admin_url('admin-post.php'),
                'deleteAction' => self::ACTION_DELETE,
            ]
        );
    }

    private function renderForm(?Provider $provider): void
    {
        $this->view->render(
            'admin/providers/form',
            [
                'provider' => $provider,
                'backUrl' => self::url(),
                'formAction' => admin_url('admin-post.php'),
                'nonceAction' => self::ACTION_SAVE,
                'saveAction' => self::ACTION_SAVE,
            ]
        );
    }

    /**
     * Collect the submitted fields.
     *
     * @return array<string, mixed>
     */
    private function readInput(): array
    {
        return [
            'name' => isset($_POST['name']) ? sanitize_text_field(wp_unslash((string) $_POST['name'])) : '',
            'api_url' => isset($_POST['api_url']) ? sanitize_text_field(wp_unslash((string) $_POST['api_url'])) : '',
            'host_url' => isset($_POST['host_url']) ? sanitize_text_field(wp_unslash((string) $_POST['host_url'])) : '',
            'email' => isset($_POST['email']) ? sanitize_email(wp_unslash((string) $_POST['email'])) : '',
            'password' => isset($_POST['password']) ? (string) wp_unslash((string) $_POST['password']) : '',
            'api_token' => isset($_POST['api_token'])
                ? sanitize_text_field(wp_unslash((string) $_POST['api_token']))
                : '',
            'region' => isset($_POST['region']) ? sanitize_text_field(wp_unslash((string) $_POST['region'])) : '',
            'currency' => isset($_POST['currency']) ? sanitize_text_field(wp_unslash((string) $_POST['currency'])) : '',
            'description' => isset($_POST['description'])
                ? sanitize_textarea_field(wp_unslash((string) $_POST['description']))
                : '',
            'timeout' => isset($_POST['timeout']) ? absint(wp_unslash((string) $_POST['timeout'])) : 30,
            'sort_order' => isset($_POST['sort_order']) ? absint(wp_unslash((string) $_POST['sort_order'])) : 0,
            'verify_ssl' => !empty($_POST['verify_ssl']),
            'is_active' => !empty($_POST['is_active']),
            'is_default' => !empty($_POST['is_default']),
        ];
    }

    /**
     * @param array<string, string|int> $args
     */
    private function redirect(array $args = []): void
    {
        wp_safe_redirect(self::url($args));

        exit;
    }
}
