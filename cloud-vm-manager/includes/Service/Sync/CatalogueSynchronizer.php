<?php

/**
 * Catalogue synchronisation orchestrator.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Sync;

use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Exception\ApiException;
use CloudVmManager\Model\AbstractPlan;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Model\Provider;
use CloudVmManager\Model\SyncRun;
use CloudVmManager\Repository\ProviderRepository;
use CloudVmManager\Repository\SyncRunRepository;
use CloudVmManager\Repository\ZoneRepository;
use CloudVmManager\Support\Cache;
use Throwable;

defined('ABSPATH') || exit;

/**
 * Runs every catalogue synchroniser for a provider in the right order.
 *
 * Zones come first because the image endpoint is scoped by zone, then the eight
 * pricing combinations. Each resource is recorded as its own run, so a partial
 * failure stays visible instead of being hidden behind one aggregate status.
 *
 * A lock keeps a scheduled run and a manual run from overlapping.
 */
final class CatalogueSynchronizer
{
    private const LOCK_PREFIX = 'cvm_sync_lock_';
    private const LOCK_TTL = 900;

    /**
     * @var ZoneSync
     */
    private $zones;

    /**
     * @var IsoTemplateSync
     */
    private $isos;

    /**
     * @var PlanSync[]
     */
    private $plans;

    /**
     * @var ZoneRepository
     */
    private $zoneRepository;

    /**
     * @var ProviderRepository
     */
    private $providers;

    /**
     * @var SyncRunRepository
     */
    private $runs;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param PlanSync[] $plans
     */
    public function __construct(
        ZoneSync $zones,
        IsoTemplateSync $isos,
        array $plans,
        ZoneRepository $zoneRepository,
        ProviderRepository $providers,
        SyncRunRepository $runs,
        Cache $cache,
        LoggerInterface $logger
    ) {
        $this->zones = $zones;
        $this->isos = $isos;
        $this->plans = $plans;
        $this->zoneRepository = $zoneRepository;
        $this->providers = $providers;
        $this->runs = $runs;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Synchronise every active provider.
     *
     * @return array<int, SyncResult[]> Results keyed by provider identifier.
     */
    public function syncAll(): array
    {
        $results = [];

        foreach ($this->providers->all(true) as $provider) {
            $results[$provider->id()] = $this->syncProvider($provider);
        }

        return $results;
    }

    /**
     * Synchronise the whole catalogue of one provider.
     *
     * @return SyncResult[]
     */
    public function syncProvider(Provider $provider): array
    {
        if ($this->isLocked($provider->id())) {
            return [
                SyncResult::skipped(
                    'catalogue',
                    __('A synchronisation for this provider is already running.', 'cloud-vm-manager')
                ),
            ];
        }

        $this->lock($provider->id());

        try {
            $results = [$this->run($provider, $this->zones, [])];
            $results[] = $this->syncIsoTemplates($provider);

            foreach ($this->plans as $plan) {
                $combined = null;

                foreach ([AbstractPlan::TYPE_SHARED, AbstractPlan::TYPE_DEDICATED] as $planType) {
                    $result = $this->run($provider, $plan, ['plan_type' => $planType]);
                    $combined = $combined === null ? $result : $combined->merge($result);
                }

                if ($combined !== null) {
                    $results[] = $combined;
                }
            }

            $this->cache->flush($provider->id());
            $this->providers->update($provider->id(), ['last_synced_at' => gmdate('Y-m-d H:i:s')]);

            $this->logger->info(
                sprintf('Catalogue of provider "%s" synchronised.', $provider->getName()),
                [
                    'channel' => LogEntry::CHANNEL_SYNC,
                    'provider_id' => $provider->id(),
                    'changes' => $this->totalChanges($results),
                ]
            );

            /**
             * Fires after a provider catalogue finished synchronising.
             *
             * @param Provider     $provider Provider that was synchronised.
             * @param SyncResult[] $results  One result per resource.
             */
            do_action('cloud_vm_manager_catalogue_synced', $provider, $results);

            return $results;
        } finally {
            $this->unlock($provider->id());
        }
    }

    /**
     * Synchronise the images of every active zone of a provider.
     */
    private function syncIsoTemplates(Provider $provider): SyncResult
    {
        $combined = null;

        foreach ($this->zoneRepository->forProvider($provider->id()) as $zone) {
            $result = $this->run(
                $provider,
                $this->isos,
                [
                    'zone_id' => $zone->id(),
                    'zone_remote_id' => $zone->getRemoteId(),
                ]
            );

            $combined = $combined === null ? $result : $combined->merge($result);
        }

        return $combined ?? SyncResult::success(SyncRun::RESOURCE_ISOS, []);
    }

    /**
     * Execute one synchroniser and record it as a run.
     *
     * @param array<string, mixed> $context
     */
    private function run(Provider $provider, AbstractResourceSync $sync, array $context): SyncResult
    {
        $runId = $this->runs->start($provider->id(), $sync->resource());

        try {
            $result = $sync->sync($provider, $context);
        } catch (ApiException $exception) {
            $result = SyncResult::failed($sync->resource(), $exception->getMessage());
        } catch (Throwable $exception) {
            $this->logger->exception(
                $exception,
                sprintf('Synchronising %s failed unexpectedly.', $sync->resource()),
                ['channel' => LogEntry::CHANNEL_SYNC, 'provider_id' => $provider->id()]
            );

            $result = SyncResult::failed($sync->resource(), $exception->getMessage());
        }

        $this->runs->finish(
            $runId,
            $result->status(),
            $result->counters(),
            $result->message(),
            $context
        );

        return $result;
    }

    /**
     * @param SyncResult[] $results
     */
    private function totalChanges(array $results): int
    {
        $total = 0;

        foreach ($results as $result) {
            $total += $result->changes();
        }

        return $total;
    }

    private function isLocked(int $providerId): bool
    {
        return get_transient(self::LOCK_PREFIX . $providerId) !== false;
    }

    private function lock(int $providerId): void
    {
        set_transient(self::LOCK_PREFIX . $providerId, 1, self::LOCK_TTL);
    }

    private function unlock(int $providerId): void
    {
        delete_transient(self::LOCK_PREFIX . $providerId);
    }
}
