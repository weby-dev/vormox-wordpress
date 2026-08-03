<?php

/**
 * Model contract.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Contracts;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * An entity hydrated from a database row.
 */
interface ModelInterface
{
    /**
     * Primary key value, zero when the entity has not been persisted yet.
     */
    public function id(): int;

    /**
     * Attributes cast to their PHP representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Attributes cast to their storage representation.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(): array;
}
