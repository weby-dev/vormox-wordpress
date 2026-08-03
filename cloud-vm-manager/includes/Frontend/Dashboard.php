<?php

/**
 * Customer dashboard.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Frontend;

use CloudVmManager\Admin\Access;
use CloudVmManager\Admin\View;
use CloudVmManager\Model\VmOrder;
use CloudVmManager\Repository\LogRepository;
use CloudVmManager\Service\Vm\VmMetricsService;
use CloudVmManager\Service\Vm\VmService;
use CloudVmManager\Service\Vm\WalletService;
use CloudVmManager\Support\Settings;

defined('ABSPATH') || exit;

/**
 * Renders the frontend dashboard behind a shortcode.
 *
 * A shortcode rather than a template override keeps the dashboard usable on any
 * theme and lets the store owner place it on whichever page they like.
 */
final class Dashboard
{
    public const SHORTCODE = 'cloud_vm_dashboard';
    public const ASSET_HANDLE = 'cloud-vm-manager-dashboard';

    /**
     * Machines listed per page.
     */
    private const PER_PAGE = 10;

    /**
     * @var VmService
     */
    private $machines;

    /**
     * @var VmMetricsService
     */
    private $metrics;

    /**
     * @var WalletService
     */
    private $wallet;

    /**
     * @var LogRepository
     */
    private $logs;

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var View
     */
    private $view;

    /**
     * @var bool
     */
    private $rendered = false;

    public function __construct(
        VmService $machines,
        VmMetricsService $metrics,
        WalletService $wallet,
        LogRepository $logs,
        Settings $settings,
        View $view
    ) {
        $this->machines = $machines;
        $this->metrics = $metrics;
        $this->wallet = $wallet;
        $this->logs = $logs;
        $this->settings = $settings;
        $this->view = $view;
    }

    /**
     * Register the shortcode and its assets.
     */
    public function register(): void
    {
        add_shortcode(self::SHORTCODE, [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'registerAssets']);
    }

    /**
     * Register the dashboard assets so they can be enqueued on demand.
     */
    public function registerAssets(): void
    {
        wp_register_style(
            self::ASSET_HANDLE,
            CVM_PLUGIN_URL . 'assets/css/dashboard.css',
            [],
            CVM_VERSION
        );

        wp_register_script(
            self::ASSET_HANDLE,
            CVM_PLUGIN_URL . 'assets/js/dashboard.js',
            [],
            CVM_VERSION,
            true
        );
    }

    /**
     * Render the dashboard.
     *
     * @param array<string, mixed>|string $attributes Shortcode attributes.
     */
    public function render($attributes = []): string
    {
        if (!$this->settings->getBool('enable_client_dashboard', true)) {
            return '';
        }

        if (!is_user_logged_in()) {
            return $this->view->capture(
                'frontend/login',
                ['loginUrl' => wp_login_url(get_permalink() ?: home_url('/'))]
            );
        }

        $this->enqueueAssets();

        $userId = get_current_user_id();
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read only navigation.
        $machineId = isset($_GET['cvm_machine']) ? absint(wp_unslash((string) $_GET['cvm_machine'])) : 0;
        $page = isset($_GET['cvm_page']) ? max(1, absint(wp_unslash((string) $_GET['cvm_page']))) : 1;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ($machineId > 0) {
            return $this->renderMachine($machineId, $userId);
        }

        return $this->renderList($userId, $page);
    }

    /**
     * URL of the dashboard, optionally addressing one machine.
     *
     * @param array<string, string|int> $args
     */
    public static function url(array $args = []): string
    {
        $base = get_permalink();

        if (!is_string($base) || $base === '') {
            $base = home_url('/');
        }

        return $args === [] ? $base : add_query_arg($args, $base);
    }

    /**
     * Print the machine list.
     */
    private function renderList(int $userId, int $page): string
    {
        $paginated = $this->machines->paginateFor($userId, ['page' => $page, 'per_page' => self::PER_PAGE]);
        $providerId = $this->firstProviderId($paginated['items']);

        return $this->view->capture(
            'frontend/machines',
            [
                'machines' => $paginated['items'],
                'total' => $paginated['total'],
                'page' => $paginated['page'],
                'pages' => $paginated['pages'],
                'summary' => $this->machines->summaryFor($userId),
                'wallet' => $providerId > 0 ? $this->wallet->balance($providerId) : null,
                'transactions' => $providerId > 0 ? $this->wallet->transactions($providerId, 8) : [],
                'activity' => $this->logs->forUser($userId, 8),
                'baseUrl' => self::url(),
            ]
        );
    }

    /**
     * Print one machine.
     */
    private function renderMachine(int $machineId, int $userId): string
    {
        $machine = $this->machines->findOwned($machineId, $userId);

        if ($machine === null) {
            return $this->view->capture(
                'frontend/not-found',
                ['backUrl' => self::url()]
            );
        }

        $lock = $this->machines->lockStatus($machine);

        return $this->view->capture(
            'frontend/machine',
            [
                'machine' => $machine,
                'lock' => $lock,
                'operable' => !$lock['locked'] && $machine->isProvisioned(),
                'metrics' => $this->metrics->metrics($machine),
                'storage' => $this->metrics->storage($machine),
                'timeframes' => $this->metrics->timeframes(),
                'details' => $this->machines->details($machine),
                'activity' => $this->machines->auditLogs($machine, 8),
                'localActivity' => $this->logs->forVmOrder($machine->id(), 8),
                'wallet' => $this->wallet->balance($machine->getProviderId()),
                'backUrl' => self::url(),
                'refreshInterval' => max(10, $this->settings->getInt('metrics_refresh_interval', 30)),
            ]
        );
    }

    /**
     * Enqueue the assets and hand the script its configuration.
     */
    private function enqueueAssets(): void
    {
        if ($this->rendered) {
            return;
        }

        $this->rendered = true;

        wp_enqueue_style(self::ASSET_HANDLE);
        wp_enqueue_script(self::ASSET_HANDLE);

        wp_localize_script(
            self::ASSET_HANDLE,
            'cvmDashboard',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce(Access::AJAX_NONCE),
                'refreshInterval' => max(10, $this->settings->getInt('metrics_refresh_interval', 30)) * 1000,
                'i18n' => [
                    'refreshing' => __('Refreshing…', 'cloud-vm-manager'),
                    'updated' => __('Updated just now', 'cloud-vm-manager'),
                    'failed' => __('Could not refresh the machine.', 'cloud-vm-manager'),
                    'noData' => __('No data for this period yet.', 'cloud-vm-manager'),
                ],
            ]
        );
    }

    /**
     * Provider of the customer's first machine, used for the wallet card.
     *
     * @param VmOrder[] $machines
     */
    private function firstProviderId(array $machines): int
    {
        foreach ($machines as $machine) {
            if ($machine->getProviderId() > 0) {
                return $machine->getProviderId();
            }
        }

        return 0;
    }
}
