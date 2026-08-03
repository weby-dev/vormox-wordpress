<?php

/**
 * Synchronisation screen.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Admin\Controller;

use CloudVmManager\Admin\Access;
use CloudVmManager\Admin\View;
use CloudVmManager\Cron\CronManager;
use CloudVmManager\Cron\SyncJob;
use CloudVmManager\Model\AbstractPlan;
use CloudVmManager\Model\Provider;
use CloudVmManager\Repository\BandwidthPlanRepository;
use CloudVmManager\Repository\CpuPlanRepository;
use CloudVmManager\Repository\DiskPlanRepository;
use CloudVmManager\Repository\IsoTemplateRepository;
use CloudVmManager\Repository\PricingRepository;
use CloudVmManager\Repository\RamPlanRepository;
use CloudVmManager\Repository\SyncRunRepository;
use CloudVmManager\Repository\ZoneRepository;
use CloudVmManager\Service\Provider\ProviderService;

defined('ABSPATH') || exit;

/**
 * Shows what is currently stored per provider and the history of the runs.
 */
final class SyncController
{
    public const PAGE = 'cloud-vm-manager-sync';

    /**
     * @var ProviderService
     */
    private $providers;

    /**
     * @var ZoneRepository
     */
    private $zones;

    /**
     * @var IsoTemplateRepository
     */
    private $isos;

    /**
     * @var CpuPlanRepository
     */
    private $cpu;

    /**
     * @var RamPlanRepository
     */
    private $ram;

    /**
     * @var DiskPlanRepository
     */
    private $disk;

    /**
     * @var BandwidthPlanRepository
     */
    private $bandwidth;

    /**
     * @var PricingRepository
     */
    private $pricing;

    /**
     * @var SyncRunRepository
     */
    private $runs;

    /**
     * @var CronManager
     */
    private $cron;

    /**
     * @var View
     */
    private $view;

    public function __construct(
        ProviderService $providers,
        ZoneRepository $zones,
        IsoTemplateRepository $isos,
        CpuPlanRepository $cpu,
        RamPlanRepository $ram,
        DiskPlanRepository $disk,
        BandwidthPlanRepository $bandwidth,
        PricingRepository $pricing,
        SyncRunRepository $runs,
        CronManager $cron,
        View $view
    ) {
        $this->providers = $providers;
        $this->zones = $zones;
        $this->isos = $isos;
        $this->cpu = $cpu;
        $this->ram = $ram;
        $this->disk = $disk;
        $this->bandwidth = $bandwidth;
        $this->pricing = $pricing;
        $this->runs = $runs;
        $this->cron = $cron;
        $this->view = $view;
    }

    public function render(): void
    {
        Access::assert();

        $providers = $this->providers->all();
        $rows = [];

        foreach ($providers as $provider) {
            $rows[] = [
                'provider' => $provider,
                'counts' => $this->counts($provider),
                'lastRun' => $this->runs->latestForProvider($provider->id()),
            ];
        }

        $this->view->render(
            'admin/sync',
            [
                'rows' => $rows,
                'runs' => $this->runs->latest(20),
                'nextRun' => $this->cron->nextRun(SyncJob::HOOK),
                'providersUrl' => ProvidersController::url(),
                'settingsUrl' => SettingsController::url(),
            ]
        );
    }

    /**
     * URL of the synchronisation screen.
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
     * Number of active catalogue records stored for a provider.
     *
     * @return array<string, int>
     */
    private function counts(Provider $provider): array
    {
        $active = ['provider_id' => $provider->id(), 'status' => AbstractPlan::STATUS_ACTIVE];

        return [
            'zones' => $this->zones->count($active),
            'isos' => $this->isos->count($active),
            'cpu' => $this->cpu->count($active),
            'ram' => $this->ram->count($active),
            'disk' => $this->disk->count($active),
            'bandwidth' => $this->bandwidth->count($active),
            'pricing' => $this->pricing->count($active),
        ];
    }
}
