<?php

/**
 * ISO template repository.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Repository;

use CloudVmManager\Database\TableRegistry;
use CloudVmManager\Model\IsoTemplate;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Persistence for synchronised operating system images.
 */
final class IsoTemplateRepository extends AbstractRepository
{
    protected function tableKey(): string
    {
        return TableRegistry::ISO_TEMPLATES;
    }

    protected function modelClass(): string
    {
        return IsoTemplate::class;
    }

    /**
     * @return array<string, string>
     */
    protected function columns(): array
    {
        return [
            'id' => '%d',
            'provider_id' => '%d',
            'zone_id' => '%d',
            'zone_remote_id' => '%d',
            'remote_id' => '%d',
            'iso_name' => '%s',
            'os_type' => '%s',
            'payload' => '%s',
            'checksum' => '%s',
            'status' => '%s',
            'synced_at' => '%s',
            'created_at' => '%s',
            'updated_at' => '%s',
        ];
    }

    /**
     * Images available inside a zone.
     *
     * @return IsoTemplate[]
     */
    public function forZone(int $providerId, int $zoneRemoteId, bool $activeOnly = true): array
    {
        $conditions = [
            'provider_id' => $providerId,
            'zone_remote_id' => $zoneRemoteId,
        ];

        if ($activeOnly) {
            $conditions['status'] = IsoTemplate::STATUS_ACTIVE;
        }

        /** @var IsoTemplate[] $templates */
        $templates = $this->findBy($conditions, ['order_by' => 'iso_name', 'order' => 'ASC']);

        return $templates;
    }

    /**
     * Look an image up by its backend identifier.
     */
    public function findByRemoteId(int $providerId, int $zoneRemoteId, int $remoteId): ?IsoTemplate
    {
        $template = $this->findOneBy(
            [
                'provider_id' => $providerId,
                'zone_remote_id' => $zoneRemoteId,
                'remote_id' => $remoteId,
            ]
        );

        return $template instanceof IsoTemplate ? $template : null;
    }

    /**
     * Flag images that disappeared from a zone.
     *
     * @param int[] $keepRemoteIds
     *
     * @return int Number of images marked as removed.
     */
    public function markMissingAsRemoved(int $providerId, int $zoneRemoteId, array $keepRemoteIds): int
    {
        $conditions = [
            'provider_id' => $providerId,
            'zone_remote_id' => $zoneRemoteId,
            'status' => IsoTemplate::STATUS_ACTIVE,
        ];

        if ($keepRemoteIds !== []) {
            $conditions['remote_id'] = ['operator' => 'NOT IN', 'value' => $keepRemoteIds];
        }

        $stale = $this->findBy($conditions);

        foreach ($stale as $template) {
            $this->update($template->id(), ['status' => IsoTemplate::STATUS_REMOVED]);
        }

        return count($stale);
    }
}
