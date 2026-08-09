<?php

/**
 * Account synchronisation result.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Vm;

use JsonSerializable;

defined('ABSPATH') || exit;

/**
 * What one account synchronisation changed.
 */
final class AccountSyncResult implements JsonSerializable
{
    /**
     * @var int
     */
    private $updated = 0;

    /**
     * @var int
     */
    private $imported = 0;

    /**
     * @var int
     */
    private $unchanged = 0;

    /**
     * @var string[]
     */
    private $errors = [];

    public function recordUpdated(): void
    {
        ++$this->updated;
    }

    public function recordImported(): void
    {
        ++$this->imported;
    }

    public function recordUnchanged(): void
    {
        ++$this->unchanged;
    }

    public function recordError(string $message): void
    {
        $this->errors[] = $message;
    }

    public function updated(): int
    {
        return $this->updated;
    }

    public function imported(): int
    {
        return $this->imported;
    }

    public function unchanged(): int
    {
        return $this->unchanged;
    }

    /**
     * @return string[]
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function isSuccessful(): bool
    {
        return $this->errors === [];
    }

    /**
     * Sentence describing the outcome, for a notice or an AJAX reply.
     */
    public function summary(): string
    {
        if ($this->errors !== []) {
            return implode(' ', $this->errors);
        }

        if ($this->updated === 0 && $this->imported === 0) {
            return __('Everything was already up to date.', 'cloud-vm-manager');
        }

        return sprintf(
            /* translators: 1: number of machines updated, 2: number of machines imported. */
            __('%1$d machine(s) updated, %2$d imported.', 'cloud-vm-manager'),
            $this->updated,
            $this->imported
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'updated' => $this->updated,
            'imported' => $this->imported,
            'unchanged' => $this->unchanged,
            'errors' => $this->errors,
            'summary' => $this->summary(),
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
