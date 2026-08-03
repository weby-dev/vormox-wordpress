<?php

/**
 * Base catalogue synchroniser.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Sync;

use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Exception\ApiException;
use CloudVmManager\Exception\DatabaseException;
use CloudVmManager\Http\ApiRequest;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Model\Provider;
use CloudVmManager\Repository\AbstractRepository;
use CloudVmManager\Service\Provider\ProviderGateway;
use CloudVmManager\Support\Arr;
use CloudVmManager\Support\Str;

defined('ABSPATH') || exit;

/**
 * Change detection shared by every catalogue resource.
 *
 * A record is added when its backend identifier is unknown, updated when the
 * checksum of its payload changed, and marked as removed when the backend stops
 * listing it. Records are never deleted, because an order placed earlier may
 * still reference the identifier.
 */
abstract class AbstractResourceSync
{
    protected const STATUS_ACTIVE = 'active';
    protected const STATUS_REMOVED = 'removed';

    /**
     * @var ProviderGateway
     */
    protected $gateway;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(ProviderGateway $gateway, LoggerInterface $logger)
    {
        $this->gateway = $gateway;
        $this->logger = $logger;
    }

    /**
     * Resource slug recorded with the sync run.
     */
    abstract public function resource(): string;

    /**
     * Request that lists the resource.
     *
     * @param array<string, mixed> $context
     */
    abstract protected function request(array $context): ApiRequest;

    /**
     * Repository the records are written to.
     */
    abstract protected function repository(): AbstractRepository;

    /**
     * Columns identifying one record, used for lookups and for the removal sweep.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    abstract protected function scope(Provider $provider, array $context): array;

    /**
     * Convert one backend item into row data.
     *
     * @param array<string, mixed> $item
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    abstract protected function map(array $item, Provider $provider, array $context): array;

    /**
     * Column holding the backend identifier.
     */
    protected function remoteColumn(): string
    {
        return 'remote_id';
    }

    /**
     * Backend identifier of one item.
     *
     * @param array<string, mixed> $item
     */
    protected function remoteId(array $item): int
    {
        $id = Arr::first($item, ['id', 'priceId', 'tierId'], 0);

        return is_numeric($id) ? (int) $id : 0;
    }

    /**
     * Hook invoked after a record was written, for dependent tables.
     *
     * @param array<string, mixed> $item
     * @param array<string, mixed> $context
     */
    protected function afterItem(int $recordId, array $item, Provider $provider, array $context): void
    {
    }

    /**
     * Synchronise the resource for one provider.
     *
     * @param array<string, mixed> $context
     */
    public function sync(Provider $provider, array $context = []): SyncResult
    {
        try {
            $items = $this->fetch($provider, $context);
        } catch (ApiException $exception) {
            if ($exception->statusCode() === 404) {
                return SyncResult::skipped(
                    $this->resource(),
                    __('The backend does not offer this resource.', 'cloud-vm-manager')
                );
            }

            $this->logger->error(
                sprintf('Sync of %s failed: %s', $this->resource(), $exception->getMessage()),
                [
                    'channel' => LogEntry::CHANNEL_SYNC,
                    'provider_id' => $provider->id(),
                    'status_code' => $exception->statusCode(),
                ]
            );

            return SyncResult::failed($this->resource(), $exception->getMessage());
        }

        $counters = ['added' => 0, 'updated' => 0, 'removed' => 0, 'unchanged' => 0];
        $seen = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $remoteId = $this->remoteId($item);

            if ($remoteId <= 0) {
                continue;
            }

            $seen[] = $remoteId;

            try {
                $this->store($provider, $context, $item, $remoteId, $counters);
            } catch (DatabaseException $exception) {
                $this->logger->error(
                    sprintf('Could not store %s record %d: %s', $this->resource(), $remoteId, $exception->getMessage()),
                    ['channel' => LogEntry::CHANNEL_SYNC, 'provider_id' => $provider->id()]
                );
            }
        }

        $counters['removed'] = $this->markMissing($provider, $context, $seen);

        return SyncResult::success($this->resource(), $counters);
    }

    /**
     * Read the resource list from the backend.
     *
     * @param array<string, mixed> $context
     *
     * @return array<int, mixed>
     *
     * @throws ApiException When the backend answers with a failure status.
     */
    protected function fetch(Provider $provider, array $context): array
    {
        $response = $this->gateway->request(
            $provider,
            $this->request($context),
            ['channel' => LogEntry::CHANNEL_SYNC]
        );

        $response->assertSuccessful();

        $data = $response->data();

        if (Arr::isList($data)) {
            return $data;
        }

        foreach (['data', 'items', 'content', 'results'] as $key) {
            $nested = Arr::get($data, $key);

            if (is_array($nested) && Arr::isList($nested)) {
                return $nested;
            }
        }

        return [];
    }

    /**
     * Insert or update one record and count the outcome.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $item
     * @param array<string, int>   $counters
     */
    private function store(Provider $provider, array $context, array $item, int $remoteId, array &$counters): void
    {
        $repository = $this->repository();
        $match = array_merge($this->scope($provider, $context), [$this->remoteColumn() => $remoteId]);

        $data = array_merge(
            $this->map($item, $provider, $context),
            [
                'payload' => $item,
                'checksum' => $this->checksum($item),
                'status' => self::STATUS_ACTIVE,
                'synced_at' => gmdate('Y-m-d H:i:s'),
            ]
        );

        $existing = $repository->findOneBy($match);

        if ($existing === null) {
            $recordId = $repository->insert(array_merge($match, $data));
            ++$counters['added'];

            $this->afterItem($recordId, $item, $provider, $context);

            return;
        }

        $wasRemoved = $existing->toArray()['status'] ?? '';
        $unchanged = ($existing->toArray()['checksum'] ?? '') === $data['checksum']
            && $wasRemoved === self::STATUS_ACTIVE;

        if ($unchanged) {
            $repository->update($existing->id(), ['synced_at' => $data['synced_at']]);
            ++$counters['unchanged'];

            $this->afterItem($existing->id(), $item, $provider, $context);

            return;
        }

        $repository->update($existing->id(), $data);

        if ($wasRemoved === self::STATUS_REMOVED) {
            ++$counters['added'];
        } else {
            ++$counters['updated'];
        }

        $this->afterItem($existing->id(), $item, $provider, $context);
    }

    /**
     * Mark records the backend no longer lists.
     *
     * @param array<string, mixed> $context
     * @param int[]                $seen
     */
    private function markMissing(Provider $provider, array $context, array $seen): int
    {
        $conditions = array_merge($this->scope($provider, $context), ['status' => self::STATUS_ACTIVE]);

        if ($seen !== []) {
            $conditions[$this->remoteColumn()] = ['operator' => 'NOT IN', 'value' => $seen];
        }

        $repository = $this->repository();
        $stale = $repository->findBy($conditions);

        foreach ($stale as $record) {
            $repository->update($record->id(), ['status' => self::STATUS_REMOVED]);
        }

        return count($stale);
    }

    /**
     * Stable checksum of a backend item, independent of its key order.
     *
     * @param array<string, mixed> $item
     */
    protected function checksum(array $item): string
    {
        return Str::checksum(Arr::sortRecursive($item));
    }

    /**
     * Read a string field, trying several documented and undocumented names.
     *
     * @param array<string, mixed> $item
     * @param string[]             $keys
     */
    protected function stringField(array $item, array $keys, string $default = ''): string
    {
        $value = Arr::first($item, $keys);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /**
     * Read a numeric field, falling back to the leading number of the label.
     *
     * @param array<string, mixed> $item
     * @param string[]             $keys
     */
    protected function intField(array $item, array $keys, string $labelKey = 'label'): int
    {
        $value = Arr::first($item, $keys);

        if (is_numeric($value)) {
            return (int) $value;
        }

        $label = Arr::get($item, $labelKey, '');

        if (is_string($label) && preg_match('/(\d+(?:\.\d+)?)/', $label, $matches) === 1) {
            return (int) round((float) $matches[1]);
        }

        return 0;
    }

    /**
     * Read a decimal field.
     *
     * @param array<string, mixed> $item
     * @param string[]             $keys
     */
    protected function floatField(array $item, array $keys): float
    {
        $value = Arr::first($item, $keys);

        return is_numeric($value) ? (float) $value : 0.0;
    }
}
