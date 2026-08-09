<?php

/**
 * Provisioning result.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Provisioning;

use JsonSerializable;

defined('ABSPATH') || exit;

/**
 * Outcome of one provisioning attempt.
 *
 * Three states are not failures. Pending means the request was accepted but the
 * backend has not named the machine yet. Building means it has: the machine
 * exists, its identifiers are stored and it is booting, which takes up to a
 * minute. Completed means it answered its detail endpoint and is usable.
 *
 * The distinction matters because a building machine has already been paid for.
 * It is polled quickly and never counted against the creation retry budget,
 * where a pending one may still turn out never to have been created.
 */
final class ProvisioningResult implements JsonSerializable
{
    private const STATE_COMPLETED = 'completed';
    private const STATE_BUILDING = 'building';
    private const STATE_PENDING = 'pending';
    private const STATE_FAILED = 'failed';

    /**
     * @var string
     */
    private $state;

    /**
     * @var string
     */
    private $message;

    /**
     * @var bool
     */
    private $retryable;

    /**
     * @var array<string, mixed>
     */
    private $data;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(string $state, string $message, bool $retryable, array $data)
    {
        $this->state = $state;
        $this->message = $message;
        $this->retryable = $retryable;
        $this->data = $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function completed(string $message, array $data = []): self
    {
        return new self(self::STATE_COMPLETED, $message, false, $data);
    }

    /**
     * Created and booting: the identifiers are known and stored.
     *
     * @param array<string, mixed> $data
     */
    public static function building(string $message, array $data = []): self
    {
        return new self(self::STATE_BUILDING, $message, true, $data);
    }

    /**
     * Accepted by the backend but not finished yet.
     *
     * @param array<string, mixed> $data
     */
    public static function pending(string $message, array $data = []): self
    {
        return new self(self::STATE_PENDING, $message, true, $data);
    }

    /**
     * Failed in a way another attempt could recover from.
     */
    public static function temporaryFailure(string $message): self
    {
        return new self(self::STATE_FAILED, $message, true, []);
    }

    /**
     * Failed in a way that will not change on its own.
     */
    public static function permanentFailure(string $message): self
    {
        return new self(self::STATE_FAILED, $message, false, []);
    }

    public function isCompleted(): bool
    {
        return $this->state === self::STATE_COMPLETED;
    }

    public function isBuilding(): bool
    {
        return $this->state === self::STATE_BUILDING;
    }

    public function isPending(): bool
    {
        return $this->state === self::STATE_PENDING;
    }

    public function isFailed(): bool
    {
        return $this->state === self::STATE_FAILED;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'message' => $this->message,
            'retryable' => $this->retryable,
            'data' => $this->data,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
