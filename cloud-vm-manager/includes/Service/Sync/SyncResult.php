<?php

/**
 * Synchronisation result.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Sync;

use CloudVmManager\Model\SyncRun;
use JsonSerializable;

defined('ABSPATH') || exit;

/**
 * Outcome of synchronising one catalogue resource.
 */
final class SyncResult implements JsonSerializable
{
    /**
     * @var string
     */
    private $resource;

    /**
     * @var string
     */
    private $status;

    /**
     * @var array<string, int>
     */
    private $counters;

    /**
     * @var string
     */
    private $message;

    /**
     * @param array<string, int> $counters
     */
    private function __construct(string $resource, string $status, array $counters, string $message)
    {
        $this->resource = $resource;
        $this->status = $status;
        $this->counters = array_merge(
            ['added' => 0, 'updated' => 0, 'removed' => 0, 'unchanged' => 0],
            $counters
        );
        $this->message = $message;
    }

    /**
     * @param array<string, int> $counters
     */
    public static function success(string $resource, array $counters, string $message = ''): self
    {
        return new self($resource, SyncRun::STATUS_SUCCESS, $counters, $message);
    }

    public static function failed(string $resource, string $message): self
    {
        return new self($resource, SyncRun::STATUS_FAILED, [], $message);
    }

    /**
     * The backend does not offer this resource, which is not an error.
     */
    public static function skipped(string $resource, string $message): self
    {
        return new self($resource, SyncRun::STATUS_PARTIAL, [], $message);
    }

    public function resource(): string
    {
        return $this->resource;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function isSuccessful(): bool
    {
        return $this->status === SyncRun::STATUS_SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === SyncRun::STATUS_FAILED;
    }

    public function isSkipped(): bool
    {
        return $this->status === SyncRun::STATUS_PARTIAL;
    }

    /**
     * @return array<string, int>
     */
    public function counters(): array
    {
        return $this->counters;
    }

    public function added(): int
    {
        return $this->counters['added'];
    }

    public function updated(): int
    {
        return $this->counters['updated'];
    }

    public function removed(): int
    {
        return $this->counters['removed'];
    }

    public function unchanged(): int
    {
        return $this->counters['unchanged'];
    }

    /**
     * Records that were added, updated or removed.
     */
    public function changes(): int
    {
        return $this->added() + $this->updated() + $this->removed();
    }

    /**
     * Merge another result of the same resource into this one.
     */
    public function merge(self $other): self
    {
        $counters = [];

        foreach ($this->counters as $key => $value) {
            $counters[$key] = $value + $other->counters[$key];
        }

        $status = $this->status;

        if ($other->isFailed()) {
            $status = SyncRun::STATUS_FAILED;
        } elseif ($this->isSuccessful() && $other->isSkipped()) {
            $status = SyncRun::STATUS_PARTIAL;
        }

        $message = trim($this->message . ' ' . $other->message);

        return new self($this->resource, $status, $counters, $message);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge(
            [
                'resource' => $this->resource,
                'status' => $this->status,
                'message' => $this->message,
            ],
            $this->counters
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
