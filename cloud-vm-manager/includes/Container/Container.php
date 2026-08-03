<?php

/**
 * Dependency injection container.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Container;

use CloudVmManager\Exception\ContainerException;
use CloudVmManager\Exception\ServiceNotFoundException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Lightweight container with lazy factories, shared instances and constructor autowiring.
 */
final class Container implements ContainerInterface
{
    /**
     * Registered factories.
     *
     * @var array<string, array{factory: callable, shared: bool}>
     */
    private $bindings = [];

    /**
     * Resolved shared services.
     *
     * @var array<string, mixed>
     */
    private $instances = [];

    /**
     * Identifier aliases.
     *
     * @var array<string, string>
     */
    private $aliases = [];

    /**
     * Identifiers currently being resolved, used for cycle detection.
     *
     * @var array<string, bool>
     */
    private $resolving = [];

    /**
     * Register a factory that produces a new object on every resolution.
     */
    public function bind(string $id, callable $factory): void
    {
        $this->register($id, $factory, false);
    }

    /**
     * Register a factory whose result is created once and then reused.
     */
    public function singleton(string $id, callable $factory): void
    {
        $this->register($id, $factory, true);
    }

    /**
     * Store an already constructed object.
     *
     * @param mixed $object
     */
    public function instance(string $id, $object): void
    {
        $this->instances[$id] = $object;
    }

    /**
     * Point an identifier at another identifier.
     */
    public function alias(string $alias, string $id): void
    {
        if ($alias === $id) {
            throw new ContainerException(sprintf('Service "%s" cannot be an alias of itself.', $alias));
        }

        $this->aliases[$alias] = $id;
    }

    /**
     * Decorate an already registered service.
     *
     * The extender receives the resolved service and the container, and returns
     * the service that should be used from then on.
     */
    public function extend(string $id, callable $extender): void
    {
        $id = $this->resolveAlias($id);

        if (array_key_exists($id, $this->instances)) {
            $this->instances[$id] = $extender($this->instances[$id], $this);

            return;
        }

        if (!isset($this->bindings[$id])) {
            throw new ServiceNotFoundException(
                sprintf('Service "%s" cannot be extended before it is registered.', $id)
            );
        }

        $binding = $this->bindings[$id];
        $factory = $binding['factory'];

        $this->bindings[$id]['factory'] = static function (Container $container) use ($factory, $extender) {
            return $extender($factory($container), $container);
        };
    }

    /**
     * Remove a service and any resolved instance of it.
     */
    public function forget(string $id): void
    {
        $id = $this->resolveAlias($id);

        unset($this->bindings[$id], $this->instances[$id]);
    }

    public function has(string $id): bool
    {
        $id = $this->resolveAlias($id);

        return array_key_exists($id, $this->instances)
            || isset($this->bindings[$id])
            || class_exists($id);
    }

    /**
     * @return mixed
     */
    public function get(string $id)
    {
        $id = $this->resolveAlias($id);

        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (isset($this->resolving[$id])) {
            throw new ContainerException(
                sprintf('Circular dependency detected while resolving "%s".', $id)
            );
        }

        $this->resolving[$id] = true;

        try {
            if (isset($this->bindings[$id])) {
                $binding = $this->bindings[$id];
                $service = $binding['factory']($this);

                if ($binding['shared']) {
                    $this->instances[$id] = $service;
                }

                return $service;
            }

            if (class_exists($id)) {
                return $this->build($id);
            }
        } finally {
            unset($this->resolving[$id]);
        }

        throw new ServiceNotFoundException(sprintf('Service "%s" is not registered in the container.', $id));
    }

    /**
     * Resolve a concrete class, autowiring its constructor dependencies.
     *
     * @return object
     */
    public function build(string $class)
    {
        try {
            $reflection = new ReflectionClass($class);
        } catch (ReflectionException $exception) {
            throw new ContainerException(
                sprintf('Class "%s" cannot be reflected: %s', $class, $exception->getMessage()),
                0,
                $exception
            );
        }

        if (!$reflection->isInstantiable()) {
            throw new ContainerException(sprintf('Class "%s" is not instantiable.', $class));
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $arguments[] = $this->resolveParameter($parameter, $class);
        }

        return $reflection->newInstanceArgs($arguments);
    }

    /**
     * Resolve a single constructor parameter.
     *
     * @return mixed
     */
    private function resolveParameter(ReflectionParameter $parameter, string $class)
    {
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $dependency = $type->getName();

            if ($this->has($dependency)) {
                return $this->get($dependency);
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($type !== null && $type->allowsNull()) {
            return null;
        }

        throw new ContainerException(
            sprintf('Parameter "$%s" of "%s" cannot be resolved.', $parameter->getName(), $class)
        );
    }

    private function register(string $id, callable $factory, bool $shared): void
    {
        unset($this->instances[$id]);

        $this->bindings[$id] = [
            'factory' => $factory,
            'shared' => $shared,
        ];
    }

    private function resolveAlias(string $id): string
    {
        $seen = [];

        while (isset($this->aliases[$id])) {
            if (isset($seen[$id])) {
                throw new ContainerException(sprintf('Circular alias detected for "%s".', $id));
            }

            $seen[$id] = true;
            $id = $this->aliases[$id];
        }

        return $id;
    }
}
