<?php

/**
 * Zone repository.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Repository;

use CloudVmManager\Database\TableRegistry;
use CloudVmManager\Model\Zone;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Persistence for synchronised zones.
 */
final class ZoneRepository extends AbstractRepository
{
    protected function tableKey(): string
    {
        return TableRegistry::ZONES;
    }

    protected function modelClass(): string
    {
        return Zone::class;
    }

    /**
     * @return array<string, string>
     */
    protected function columns(): array
    {
        return [
            'id' => '%d',
            'provider_id' => '%d',
            'remote_id' => '%d',
            'name' => '%s',
            'country' => '%s',
            'description' => '%s',
            'payload' => '%s',
            'checksum' => '%s',
            'status' => '%s',
            'synced_at' => '%s',
            'created_at' => '%s',
            'updated_at' => '%s',
        ];
    }

    /**
     * Active zones of a provider, ordered by name.
     *
     * @return Zone[]
     */
    public function forProvider(int $providerId, bool $activeOnly = true): array
    {
        $conditions = ['provider_id' => $providerId];

        if ($activeOnly) {
            $conditions['status'] = Zone::STATUS_ACTIVE;
        }

        /** @var Zone[] $zones */
        $zones = $this->findBy($conditions, ['order_by' => 'name', 'order' => 'ASC']);

        return $zones;
    }

    /**
     * Look a zone up by its backend identifier.
     */
    public function findByRemoteId(int $providerId, int $remoteId): ?Zone
    {
        $zone = $this->findOneBy(['provider_id' => $providerId, 'remote_id' => $remoteId]);

        return $zone instanceof Zone ? $zone : null;
    }

    /**
     * Backend identifiers currently stored for a provider.
     *
     * @return int[]
     */
    public function remoteIds(int $providerId): array
    {
        $sql = 'SELECT remote_id FROM `' . $this->table() . '` WHERE provider_id = %d';
        $rows = $this->results($sql, [$providerId]);

        return array_map(
            static function (array $row): int {
                return (int) $row['remote_id'];
            },
            $rows
        );
    }

    /**
     * Flag zones that disappeared from the backend.
     *
     * @param int[] $keepRemoteIds
     *
     * @return int Number of zones marked as removed.
     */
    public function markMissingAsRemoved(int $providerId, array $keepRemoteIds): int
    {
        $conditions = [
            'provider_id' => $providerId,
            'status' => Zone::STATUS_ACTIVE,
        ];

        if ($keepRemoteIds !== []) {
            $conditions['remote_id'] = ['operator' => 'NOT IN', 'value' => $keepRemoteIds];
        }

        $stale = $this->findBy($conditions);

        foreach ($stale as $zone) {
            $this->update($zone->id(), ['status' => Zone::STATUS_REMOVED]);
        }

        return count($stale);
    }
}
