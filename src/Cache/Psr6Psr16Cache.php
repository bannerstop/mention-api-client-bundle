<?php

declare(strict_types=1);

namespace Bannerstop\MentionApiClientBundle\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * A PSR-16 cache on top of a PSR-6 pool, for psr/simple-cache 3 together with a symfony/cache version whose own
 * Psr16Cache cannot work with it (symfony/cache 5.4). Everything else uses Symfony's Psr16Cache.
 *
 * The class cannot be loaded with psr/simple-cache 1 or 2, which is why nothing else refers to it.
 *
 * @internal
 */
final class Psr6Psr16Cache implements CacheInterface
{
    private CacheItemPoolInterface $pool;

    public function __construct(CacheItemPoolInterface $pool)
    {
        $this->pool = $pool;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $item = $this->pool->getItem($key);

        return $item->isHit() ? $item->get() : $default;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $item = $this->pool->getItem($key)->set($value);
        if ($ttl instanceof \DateInterval) {
            $item->expiresAfter($ttl);
        } elseif (null !== $ttl) {
            if ($ttl <= 0) {
                return $this->pool->deleteItem($key);
            }
            $item->expiresAfter($ttl);
        }

        return $this->pool->save($item);
    }

    public function delete(string $key): bool
    {
        return $this->pool->deleteItem($key);
    }

    public function clear(): bool
    {
        return $this->pool->clear();
    }

    /**
     * @param iterable<string> $keys
     *
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        $saved = true;
        foreach ($values as $key => $value) {
            $saved = $this->set((string) $key, $value, $ttl) && $saved;
        }

        return $saved;
    }

    /**
     * @param iterable<string> $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $deleted = true;
        foreach ($keys as $key) {
            $deleted = $this->pool->deleteItem($key) && $deleted;
        }

        return $deleted;
    }

    public function has(string $key): bool
    {
        return $this->pool->hasItem($key);
    }
}
