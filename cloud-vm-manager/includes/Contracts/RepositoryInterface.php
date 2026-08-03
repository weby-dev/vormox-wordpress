<?php

/**
 * Repository contract.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Contracts;

use CloudVmManager\Exception\DatabaseException;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Persistence gateway for a single table.
 *
 * Implementations are the only place in the plugin allowed to build SQL.
 */
interface RepositoryInterface
{
    /**
     * Fully prefixed table name.
     */
    public function table(): string;

    /**
     * Find a single row by primary key.
     */
    public function find(int $id): ?ModelInterface;

    /**
     * Find the first row matching the conditions.
     *
     * @param array<string, mixed> $conditions
     */
    public function findOneBy(array $conditions): ?ModelInterface;

    /**
     * Find every row matching the conditions.
     *
     * @param array<string, mixed> $conditions
     * @param array<string, mixed> $args       Supports order_by, order, limit and offset.
     *
     * @return ModelInterface[]
     */
    public function findBy(array $conditions, array $args = []): array;

    /**
     * Count the rows matching the conditions.
     *
     * @param array<string, mixed> $conditions
     */
    public function count(array $conditions = []): int;

    /**
     * Insert a row and return its primary key.
     *
     * @param array<string, mixed> $data
     *
     * @throws DatabaseException When the statement fails.
     */
    public function insert(array $data): int;

    /**
     * Update a row by primary key.
     *
     * @param array<string, mixed> $data
     *
     * @throws DatabaseException When the statement fails.
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete a row by primary key.
     */
    public function delete(int $id): bool;
}
