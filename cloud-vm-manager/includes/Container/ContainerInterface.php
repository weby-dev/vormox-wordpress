<?php

/**
 * Container contract.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Container;

use CloudVmManager\Exception\ContainerException;
use CloudVmManager\Exception\ServiceNotFoundException;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Read interface of the dependency injection container.
 *
 * The signature intentionally mirrors PSR-11 so the container can be swapped for
 * any PSR-11 implementation without touching consumer code.
 */
interface ContainerInterface
{
    /**
     * Resolve an entry.
     *
     * @param string $id Service identifier, usually a fully qualified class name.
     *
     * @return mixed
     *
     * @throws ServiceNotFoundException When no entry matches the identifier.
     * @throws ContainerException       When the entry cannot be built.
     */
    public function get(string $id);

    /**
     * Whether the container can return an entry for the given identifier.
     */
    public function has(string $id): bool;
}
