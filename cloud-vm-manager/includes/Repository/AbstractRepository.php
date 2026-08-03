<?php

/**
 * Base repository.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Repository;

use CloudVmManager\Contracts\ModelInterface;
use CloudVmManager\Contracts\RepositoryInterface;
use CloudVmManager\Database\TableRegistry;
use CloudVmManager\Exception\DatabaseException;
use wpdb;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Shared persistence logic for every plugin table.
 *
 * Column names are validated against the declared column map before they reach a
 * statement and every value is bound through `wpdb::prepare()`, so neither an
 * identifier nor a value can be injected.
 */
abstract class AbstractRepository implements RepositoryInterface
{
    /**
     * Comparison operators accepted in condition arrays.
     *
     * @var string[]
     */
    private const OPERATORS = [
        '=',
        '!=',
        '<>',
        '<',
        '<=',
        '>',
        '>=',
        'LIKE',
        'NOT LIKE',
        'IN',
        'NOT IN',
        'IS NULL',
        'IS NOT NULL',
    ];

    /**
     * @var wpdb
     */
    protected $wpdb;

    /**
     * @var TableRegistry
     */
    protected $tables;

    public function __construct(wpdb $wpdb, TableRegistry $tables)
    {
        $this->wpdb = $wpdb;
        $this->tables = $tables;
    }

    /**
     * Table key handled by the repository.
     */
    abstract protected function tableKey(): string;

    /**
     * Column to printf format map, used for validation and binding.
     *
     * @return array<string, string>
     */
    abstract protected function columns(): array;

    /**
     * Model class the rows are hydrated into.
     *
     * @return class-string<ModelInterface>
     */
    abstract protected function modelClass(): string;

    public function table(): string
    {
        return $this->tables->name($this->tableKey());
    }

    public function primaryKey(): string
    {
        return 'id';
    }

    /**
     * Whether the table carries created_at and updated_at columns.
     */
    protected function hasTimestamps(): bool
    {
        $columns = $this->columns();

        return isset($columns['created_at'], $columns['updated_at']);
    }

    public function find(int $id): ?ModelInterface
    {
        return $this->findOneBy([$this->primaryKey() => $id]);
    }

    /**
     * @param array<string, mixed> $conditions
     */
    public function findOneBy(array $conditions): ?ModelInterface
    {
        $results = $this->findBy($conditions, ['limit' => 1]);

        return $results === [] ? null : $results[0];
    }

    /**
     * @param array<string, mixed> $conditions
     * @param array<string, mixed> $args
     *
     * @return ModelInterface[]
     */
    public function findBy(array $conditions, array $args = []): array
    {
        $params = [];
        $sql = 'SELECT * FROM `' . $this->table() . '`'
            . $this->buildWhere($conditions, $params)
            . $this->buildOrderBy($args)
            . $this->buildLimit($args, $params);

        $rows = $this->results($sql, $params);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Page through the table.
     *
     * @param array<string, mixed> $conditions
     * @param array<string, mixed> $args
     *
     * @return array{items: ModelInterface[], total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $conditions = [], array $args = []): array
    {
        $page = max(1, (int) ($args['page'] ?? 1));
        $perPage = (int) ($args['per_page'] ?? 20);
        $perPage = $perPage > 0 ? min($perPage, 200) : 20;

        $total = $this->count($conditions);

        $items = $this->findBy(
            $conditions,
            [
                'order_by' => $args['order_by'] ?? $this->primaryKey(),
                'order' => $args['order'] ?? 'DESC',
                'limit' => $perPage,
                'offset' => ($page - 1) * $perPage,
            ]
        );

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
        ];
    }

    /**
     * @param array<string, mixed> $conditions
     */
    public function count(array $conditions = []): int
    {
        $params = [];
        $sql = 'SELECT COUNT(*) FROM `' . $this->table() . '`' . $this->buildWhere($conditions, $params);

        return (int) $this->scalar($sql, $params);
    }

    /**
     * Whether at least one row matches the conditions.
     *
     * @param array<string, mixed> $conditions
     */
    public function exists(array $conditions): bool
    {
        return $this->count($conditions) > 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $data = $this->prepareData($data, true);

        if ($data === []) {
            throw new DatabaseException(sprintf('Nothing to insert into "%s".', $this->table()));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $this->wpdb->insert($this->table(), $data, $this->formatsFor(array_keys($data)));

        if ($result === false) {
            throw new DatabaseException(
                sprintf('Insert into "%s" failed: %s', $this->table(), (string) $this->wpdb->last_error)
            );
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $data = $this->prepareData($data, false);

        if ($data === []) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $this->wpdb->update(
            $this->table(),
            $data,
            [$this->primaryKey() => $id],
            $this->formatsFor(array_keys($data)),
            ['%d']
        );

        if ($result === false) {
            throw new DatabaseException(
                sprintf('Update of "%s" failed: %s', $this->table(), (string) $this->wpdb->last_error)
            );
        }

        return true;
    }

    /**
     * Insert a row, or update the existing row matching the unique conditions.
     *
     * @param array<string, mixed> $match Unique key columns and values.
     * @param array<string, mixed> $data  Values to write.
     *
     * @return int Primary key of the affected row.
     */
    public function upsert(array $match, array $data): int
    {
        $existing = $this->findOneBy($match);

        if ($existing !== null) {
            $this->update($existing->id(), $data);

            return $existing->id();
        }

        return $this->insert(array_merge($match, $data));
    }

    public function delete(int $id): bool
    {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $this->wpdb->delete($this->table(), [$this->primaryKey() => $id], ['%d']);

        return $result !== false && $result > 0;
    }

    /**
     * Delete every row matching the conditions.
     *
     * @param array<string, mixed> $conditions
     *
     * @return int Number of deleted rows.
     */
    public function deleteWhere(array $conditions): int
    {
        if ($conditions === []) {
            throw new DatabaseException('Refusing to delete every row without conditions.');
        }

        $params = [];
        $sql = 'DELETE FROM `' . $this->table() . '`' . $this->buildWhere($conditions, $params);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $result = $this->wpdb->query($this->prepareStatement($sql, $params));

        return $result === false ? 0 : (int) $result;
    }

    /**
     * Remove every row of the table.
     */
    public function truncate(): void
    {
        $table = $this->table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $this->wpdb->query("TRUNCATE TABLE `{$table}`");
    }

    /**
     * Hydrate a raw row into its model.
     *
     * @param array<string, mixed> $row
     */
    public function hydrate(array $row): ModelInterface
    {
        $class = $this->modelClass();

        return new $class($row);
    }

    /**
     * Current UTC timestamp in MySQL format.
     */
    protected function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Filter out unknown columns and manage timestamps.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function prepareData(array $data, bool $isInsert): array
    {
        $columns = $this->columns();
        $prepared = [];

        foreach ($data as $column => $value) {
            $column = (string) $column;

            if (!isset($columns[$column]) || $column === $this->primaryKey()) {
                continue;
            }

            $prepared[$column] = $this->normalizeValue($value, $columns[$column]);
        }

        if ($this->hasTimestamps()) {
            $now = $this->now();

            if ($isInsert && !isset($prepared['created_at'])) {
                $prepared['created_at'] = $now;
            }

            $prepared['updated_at'] = $prepared['updated_at'] ?? $now;
        }

        return $prepared;
    }

    /**
     * Convert a PHP value into something wpdb can bind.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    protected function normalizeValue($value, string $format)
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return (string) wp_json_encode($value);
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if ($format === '%d') {
            return (int) $value;
        }

        if ($format === '%f') {
            return (float) $value;
        }

        return (string) $value;
    }

    /**
     * Printf formats for the given columns, in the same order.
     *
     * @param string[] $columns
     *
     * @return string[]
     */
    protected function formatsFor(array $columns): array
    {
        $map = $this->columns();
        $formats = [];

        foreach ($columns as $column) {
            $formats[] = $map[$column] ?? '%s';
        }

        return $formats;
    }

    /**
     * Validate a column name against the declared column map.
     *
     * @throws DatabaseException When the column does not belong to the table.
     */
    protected function assertColumn(string $column): string
    {
        if (!isset($this->columns()[$column])) {
            throw new DatabaseException(
                sprintf('Unknown column "%s" for table "%s".', $column, $this->table())
            );
        }

        return $column;
    }

    /**
     * Build a WHERE clause and collect its bound parameters.
     *
     * Supported condition shapes:
     *   'column' => 'value'                        Equality.
     *   'column' => null                           IS NULL.
     *   'column' => [1, 2, 3]                      IN (...).
     *   'column' => ['operator' => '>=', 'value' => 5]
     *
     * @param array<string, mixed> $conditions
     * @param array<int, mixed>    $params
     */
    protected function buildWhere(array $conditions, array &$params): string
    {
        if ($conditions === []) {
            return '';
        }

        $clauses = [];

        foreach ($conditions as $column => $value) {
            $column = $this->assertColumn((string) $column);
            $format = $this->columns()[$column];

            if (is_array($value) && isset($value['operator'])) {
                $clauses[] = $this->buildOperatorClause($column, $format, $value, $params);

                continue;
            }

            if (is_array($value)) {
                $clauses[] = $this->buildInClause($column, $format, $value, 'IN', $params);

                continue;
            }

            if ($value === null) {
                $clauses[] = "`{$column}` IS NULL";

                continue;
            }

            $params[] = $this->normalizeValue($value, $format);
            $clauses[] = "`{$column}` = {$format}";
        }

        return ' WHERE ' . implode(' AND ', $clauses);
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<int, mixed>    $params
     */
    private function buildOperatorClause(string $column, string $format, array $condition, array &$params): string
    {
        $operator = strtoupper(trim((string) $condition['operator']));

        if (!in_array($operator, self::OPERATORS, true)) {
            throw new DatabaseException(sprintf('Unsupported comparison operator "%s".', $operator));
        }

        $value = $condition['value'] ?? null;

        if ($operator === 'IS NULL' || $operator === 'IS NOT NULL') {
            return "`{$column}` {$operator}";
        }

        if ($operator === 'IN' || $operator === 'NOT IN') {
            return $this->buildInClause($column, $format, (array) $value, $operator, $params);
        }

        if ($operator === 'LIKE' || $operator === 'NOT LIKE') {
            $params[] = '%' . $this->wpdb->esc_like((string) $value) . '%';

            return "`{$column}` {$operator} %s";
        }

        $params[] = $this->normalizeValue($value, $format);

        return "`{$column}` {$operator} {$format}";
    }

    /**
     * @param array<int, mixed> $values
     * @param array<int, mixed> $params
     */
    private function buildInClause(
        string $column,
        string $format,
        array $values,
        string $operator,
        array &$params
    ): string {
        if ($values === []) {
            return $operator === 'IN' ? '1 = 0' : '1 = 1';
        }

        foreach ($values as $value) {
            $params[] = $this->normalizeValue($value, $format);
        }

        $placeholders = implode(', ', array_fill(0, count($values), $format));

        return "`{$column}` {$operator} ({$placeholders})";
    }

    /**
     * @param array<string, mixed> $args
     */
    protected function buildOrderBy(array $args): string
    {
        if (!isset($args['order_by'])) {
            return '';
        }

        $column = $this->assertColumn((string) $args['order_by']);
        $direction = strtoupper((string) ($args['order'] ?? 'ASC'));
        $direction = $direction === 'DESC' ? 'DESC' : 'ASC';

        return " ORDER BY `{$column}` {$direction}";
    }

    /**
     * @param array<string, mixed> $args
     * @param array<int, mixed>    $params
     */
    protected function buildLimit(array $args, array &$params): string
    {
        if (!isset($args['limit'])) {
            return '';
        }

        $limit = max(0, (int) $args['limit']);

        if ($limit === 0) {
            return '';
        }

        $params[] = $limit;
        $sql = ' LIMIT %d';

        if (isset($args['offset'])) {
            $params[] = max(0, (int) $args['offset']);
            $sql .= ' OFFSET %d';
        }

        return $sql;
    }

    /**
     * Bind the parameters into the statement.
     *
     * @param array<int, mixed> $params
     */
    protected function prepareStatement(string $sql, array $params): string
    {
        if ($params === []) {
            return $sql;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (string) $this->wpdb->prepare($sql, $params);
    }

    /**
     * Run a SELECT and return the raw rows.
     *
     * @param array<int, mixed> $params
     *
     * @return array<int, array<string, mixed>>
     */
    protected function results(string $sql, array $params = []): array
    {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $rows = $this->wpdb->get_results($this->prepareStatement($sql, $params), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Run a SELECT and return the first column of the first row.
     *
     * @param array<int, mixed> $params
     *
     * @return mixed
     */
    protected function scalar(string $sql, array $params = [])
    {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return $this->wpdb->get_var($this->prepareStatement($sql, $params));
    }
}
