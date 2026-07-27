<?php declare(strict_types=1);

namespace WPM\Infrastructure\DI;

defined('ABSPATH') || exit;

/**
 * Lightweight Dependency Injection Container for WooCommerce Price Manager.
 */
final class Container implements ContainerInterface
{
    /** @var array<string, callable> */
    private array $resolvers = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, bool> */
    private array $singletons = [];

    /**
     * Bind a resolver callback to an identifier.
     *
     * @param string   $id        Identifier (usually class or interface name).
     * @param callable $resolver  Factory callback fn(ContainerInterface $c): mixed.
     * @param bool     $singleton Whether to cache the resolved instance.
     */
    public function bind(string $id, callable $resolver, bool $singleton = true): void
    {
        $this->resolvers[$id] = $resolver;
        $this->singletons[$id] = $singleton;
        unset($this->instances[$id]);
    }

    /**
     * Bind a pre-instantiated object directly into the container.
     *
     * @param string $id       Identifier.
     * @param mixed  $instance Pre-existing object or value.
     */
    public function instance(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
        $this->singletons[$id] = true;
        unset($this->resolvers[$id]);
    }

    /**
     * Resolve and retrieve an entry from the container.
     *
     * @param string $id Identifier of the entry.
     * @return mixed Resolved instance.
     * @throws NotFoundException When entry identifier is not bound.
     * @throws ContainerException When resolution fails.
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!isset($this->resolvers[$id])) {
            throw new NotFoundException(sprintf('No container binding found for identifier: %s', $id));
        }

        try {
            $instance = ($this->resolvers[$id])($this);
        } catch (\Throwable $e) {
            throw new ContainerException(sprintf('Error resolving identifier "%s": %s', $id, $e->getMessage()), 0, $e);
        }

        if (!empty($this->singletons[$id])) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    /**
     * Check if the container has a binding or instance for the identifier.
     *
     * @param string $id Identifier.
     * @return bool
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->instances) || isset($this->resolvers[$id]);
    }
}
