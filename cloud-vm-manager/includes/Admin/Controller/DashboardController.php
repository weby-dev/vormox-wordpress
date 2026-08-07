<?php

/**
 * Admin dashboard.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Admin\Controller;

use CloudVmManager\Admin\Access;
use CloudVmManager\Admin\View;
use CloudVmManager\Cron\CronManager;
use CloudVmManager\Cron\ProvisioningJob;
use CloudVmManager\Cron\SyncJob;
use CloudVmManager\Database\Installer;
use CloudVmManager\Model\Provider;
use CloudVmManager\Model\VmOrder;
use CloudVmManager\Repository\LogRepository;
use CloudVmManager\Repository\ProviderRepository;
use CloudVmManager\Repository\SyncRunRepository;
use CloudVmManager\Repository\VmOrderRepository;

defined('ABSPATH') || exit;

/**
 * Landing screen summarising the state of the whole integration.
 *
 * Everything here is read from the local tables, so opening the dashboard
 * never waits on a provider.
 */
final class DashboardController
{
    public const PAGE = 'cloud-vm-manager';

    /**
     * @var ProviderRepository
     */
    private $providers;

    /**
     * @var VmOrderRepository
     */
    private $orders;

    /**
     * @var SyncRunRepository
     */
    private $runs;

    /**
     * @var LogRepository
     */
    private $logs;

    /**
     * @var CronManager
     */
    private $cron;

    /**
     * @var Installer
     */
    private $installer;

    /**
     * @var View
     */
    private $view;

    public function __construct(
        ProviderRepository $providers,
        VmOrderRepository $orders,
        SyncRunRepository $runs,
        LogRepository $logs,
        CronManager $cron,
        Installer $installer,
        View $view
    ) {
        $this->providers = $providers;
        $this->orders = $orders;
        $this->runs = $runs;
        $this->logs = $logs;
        $this->cron = $cron;
        $this->installer = $installer;
        $this->view = $view;
    }

    public function render(): void
    {
        Access::assert();

        $providers = $this->providers->all();
        $statusCounts = $this->orders->countByStatus();

        $this->view->render(
            'admin/dashboard',
            [
                'stats' => [
                    'providers' => count($providers),
                    'connected' => $this->countConnected($providers),
                    'customers' => $this->orders->countCustomers(),
                    'machines' => array_sum($statusCounts),
                    'active' => $statusCounts[VmOrder::STATUS_ACTIVE] ?? 0,
                    'provisioning' => $statusCounts[VmOrder::STATUS_PROVISIONING] ?? 0,
                    'failed' => $statusCounts[VmOrder::STATUS_FAILED] ?? 0,
                    'revenue' => $this->orders->totalRevenue(),
                ],
                'providers' => $providers,
                'runs' => $this->runs->latest(5),
                'logs' => $this->logs->latest(10),
                'health' => $this->health($providers),
                'schedule' => [
                    'sync' => $this->cron->nextRun(SyncJob::HOOK),
                    'provisioning' => $this->cron->nextRun(ProvisioningJob::HOOK),
                ],
                'urls' => [
                    'providers' => ProvidersController::url(),
                    'machines' => VmOrdersController::url(),
                    'sync' => SyncController::url(),
                    'settings' => SettingsController::url(),
                ],
            ]
        );
    }

    /**
     * URL of the dashboard screen.
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

    /**
     * Conditions worth an administrator's attention.
     *
     * @param Provider[] $providers
     *
     * @return array<int, array{level: string, message: string}>
     */
    private function health(array $providers): array
    {
        $issues = [];
        $missingTables = $this->installer->missingTables();

        if ($missingTables !== []) {
            $issues[] = [
                'level' => 'error',
                'message' => sprintf(
                    /* translators: %s: comma separated list of table names. */
                    __('Database tables are missing: %s. Deactivate and reactivate the plugin.', 'cloud-vm-manager'),
                    implode(', ', $missingTables)
                ),
            ];
        }

        if ($providers === []) {
            $issues[] = [
                'level' => 'warning',
                'message' => __('No cloud provider is configured yet.', 'cloud-vm-manager'),
            ];
        }

        foreach ($providers as $provider) {
            if ($provider->getStatus() === Provider::STATUS_ERROR) {
                $issues[] = [
                    'level' => 'error',
                    'message' => sprintf(
                        /* translators: 1: provider name, 2: error message. */
                        __('Provider "%1$s" reported an error: %2$s', 'cloud-vm-manager'),
                        $provider->getName(),
                        $provider->getLastError()
                    ),
                ];

                continue;
            }

            if ($provider->isActive() && !$provider->isConnected()) {
                $issues[] = [
                    'level' => 'warning',
                    'message' => sprintf(
                        /* translators: %s: provider name. */
                        __('Provider "%s" is not connected yet.', 'cloud-vm-manager'),
                        $provider->getName()
                    ),
                ];
            }
        }

        $failed = $this->orders->count(['status' => VmOrder::STATUS_FAILED]);

        if ($failed > 0) {
            $issues[] = [
                'level' => 'error',
                'message' => sprintf(
                    /* translators: %d: number of machines. */
                    _n(
                        '%d machine could not be provisioned.',
                        '%d machines could not be provisioned.',
                        $failed,
                        'cloud-vm-manager'
                    ),
                    $failed
                ),
            ];
        }

        if ($issues === []) {
            $issues[] = [
                'level' => 'success',
                'message' => __('Everything is running normally.', 'cloud-vm-manager'),
            ];
        }

        return $issues;
    }

    /**
     * @param Provider[] $providers
     */
    private function countConnected(array $providers): int
    {
        $connected = 0;

        foreach ($providers as $provider) {
            if ($provider->isConnected()) {
                ++$connected;
            }
        }

        return $connected;
    }
}
