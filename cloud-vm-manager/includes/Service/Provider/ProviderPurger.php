<?php

/**
 * Provider owned data removal.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Provider;

use CloudVmManager\Repository\AbstractRepository;
use CloudVmManager\Repository\BandwidthPlanRepository;
use CloudVmManager\Repository\CacheRepository;
use CloudVmManager\Repository\CpuPlanRepository;
use CloudVmManager\Repository\CustomerAccountRepository;
use CloudVmManager\Repository\DiskPlanRepository;
use CloudVmManager\Repository\IsoTemplateRepository;
use CloudVmManager\Repository\PricingRepository;
use CloudVmManager\Repository\RamPlanRepository;
use CloudVmManager\Repository\SyncRunRepository;
use CloudVmManager\Repository\ZoneRepository;

defined('ABSPATH') || exit;

/**
 * Removes everything a provider owns when the provider itself is removed.
 *
 * Only synchronised catalogue data, cached responses and the local account
 * mapping are purged. Machines and log entries are deliberately kept: they are
 * the record of what a customer bought and what was done to it, and that record
 * has to outlive the provider row it was bought through.
 */
final class ProviderPurger
{
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
     * @var CacheRepository
     */
    private $cache;

    /**
     * @var CustomerAccountRepository
     */
    private $accounts;

    public function __construct(
        ZoneRepository $zones,
        IsoTemplateRepository $isos,
        CpuPlanRepository $cpu,
        RamPlanRepository $ram,
        DiskPlanRepository $disk,
        BandwidthPlanRepository $bandwidth,
        PricingRepository $pricing,
        SyncRunRepository $runs,
        CacheRepository $cache,
        CustomerAccountRepository $accounts
    ) {
        $this->zones = $zones;
        $this->isos = $isos;
        $this->cpu = $cpu;
        $this->ram = $ram;
        $this->disk = $disk;
        $this->bandwidth = $bandwidth;
        $this->pricing = $pricing;
        $this->runs = $runs;
        $this->cache = $cache;
        $this->accounts = $accounts;
    }

    /**
     * Delete every row the given provider owns.
     *
     * @return int Number of rows removed.
     */
    public function purge(int $providerId): int
    {
        $removed = 0;

        foreach ($this->repositories() as $repository) {
            $removed += $repository->deleteWhere(['provider_id' => $providerId]);
        }

        return $removed;
    }

    /**
     * @return AbstractRepository[]
     */
    private function repositories(): array
    {
        return [
            $this->pricing,
            $this->isos,
            $this->zones,
            $this->cpu,
            $this->ram,
            $this->disk,
            $this->bandwidth,
            $this->runs,
            $this->cache,
            $this->accounts,
        ];
    }
}
