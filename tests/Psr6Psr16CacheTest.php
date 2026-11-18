<?php

declare(strict_types=1);

namespace Bannerstop\MentionApiClientBundle\Tests;

use Bannerstop\MentionApiClientBundle\Cache\Psr6Psr16Cache;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * The adapter for psr/simple-cache 3 next to a symfony/cache without a working Psr16Cache.
 */
final class Psr6Psr16CacheTest extends TestCase
{
    protected function setUp(): void
    {
        if (!(new \ReflectionMethod(CacheInterface::class, 'get'))->hasReturnType()) {
            self::markTestSkipped('The adapter needs psr/simple-cache 3.');
        }
    }

    public function testValuesAreStoredReadAndDeleted(): void
    {
        $cache = new Psr6Psr16Cache(new ArrayAdapter());

        self::assertSame('fallback', $cache->get('token', 'fallback'));
        self::assertTrue($cache->set('token', ['access_token' => 'abc'], 60));
        self::assertTrue($cache->has('token'));
        self::assertSame(['access_token' => 'abc'], $cache->get('token'));
        self::assertTrue($cache->delete('token'));
        self::assertFalse($cache->has('token'));
    }

    public function testAValueWithoutPositiveLifetimeIsNotKept(): void
    {
        $cache = new Psr6Psr16Cache(new ArrayAdapter());

        $cache->set('token', 'abc', 0);
        $cache->set('other', 'abc', new \DateInterval('PT1M'));

        self::assertFalse($cache->has('token'));
        self::assertTrue($cache->has('other'));
    }

    public function testSeveralValuesAtOnce(): void
    {
        $cache = new Psr6Psr16Cache(new ArrayAdapter());

        $cache->setMultiple(['a' => 1, 'b' => 2]);

        self::assertSame(['a' => 1, 'b' => 2, 'c' => null], $cache->getMultiple(['a', 'b', 'c']));
        self::assertTrue($cache->deleteMultiple(['a', 'b']));
        self::assertFalse($cache->has('a'));
        $cache->set('d', 4);
        self::assertTrue($cache->clear());
        self::assertFalse($cache->has('d'));
    }
}
