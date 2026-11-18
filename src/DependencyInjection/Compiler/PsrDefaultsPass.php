<?php

declare(strict_types=1);

namespace Bannerstop\MentionApiClientBundle\DependencyInjection\Compiler;

use Bannerstop\MentionApiClientBundle\DependencyInjection\BannerstopMentionApiClientExtension;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Wires the PSR services the client needs, with sensible defaults.
 *
 * @internal
 */
final class PsrDefaultsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('bannerstop_mention_api_client.psr')) {
            return;
        }
        $psr = $container->getParameter('bannerstop_mention_api_client.psr');
        if (!\is_array($psr)) {
            return;
        }

        $factories = null;
        if (null !== $psr['http_client']) {
            if (!$container->has($psr['http_client'])) {
                throw new LogicException(sprintf('bannerstop_mention_api_client.http_client refers to the service "%s", which does not exist.', $psr['http_client']));
            }
            $container->setAlias(BannerstopMentionApiClientExtension::HTTP_CLIENT, $psr['http_client']);
        } else {
            if (!$container->has('http_client') || !class_exists(Psr18Client::class) || !class_exists(Psr17Factory::class)) {
                throw new LogicException('bannerstop_mention_api_client needs a PSR-18 HTTP client. Install symfony/http-client and nyholm/psr7 and enable the framework http_client, or set "http_client" to the service id of a PSR-18 client.');
            }
            $container->register('bannerstop_mention_api_client.symfony_http_client', HttpClientInterface::class)
                ->setFactory([new Reference('http_client'), 'withOptions'])
                ->setArguments([['timeout' => '%bannerstop_mention_api_client.timeout%', 'max_duration' => '%bannerstop_mention_api_client.timeout%']]);
            $container->register(BannerstopMentionApiClientExtension::HTTP_CLIENT, Psr18Client::class)
                ->setArguments([new Reference('bannerstop_mention_api_client.symfony_http_client')]);
            $factories = BannerstopMentionApiClientExtension::HTTP_CLIENT;
        }

        $this->cache($container, $psr['cache']);
        $this->factory($container, BannerstopMentionApiClientExtension::REQUEST_FACTORY, $psr['request_factory'], RequestFactoryInterface::class, $factories);
        $this->factory($container, BannerstopMentionApiClientExtension::STREAM_FACTORY, $psr['stream_factory'], StreamFactoryInterface::class, $factories);
    }

    private function cache(ContainerBuilder $container, ?string $cache): void
    {
        if (null === $cache) {
            return;
        }
        if (!$container->has($cache)) {
            throw new LogicException(sprintf('bannerstop_mention_api_client.cache refers to the service "%s", which does not exist. Set it to a cache pool, or to null to go without a cache.', $cache));
        }
        $class = $this->serviceClass($container, $cache);
        if (null !== $class && is_subclass_of($class, CacheInterface::class)) {
            $container->setAlias(BannerstopMentionApiClientExtension::CACHE, $cache);
        } elseif (null !== $class && is_subclass_of($class, CacheItemPoolInterface::class)) {
            $container->register(BannerstopMentionApiClientExtension::CACHE, Psr16Cache::class)->setArguments([new Reference($cache)]);
        } else {
            throw new LogicException(sprintf('bannerstop_mention_api_client.cache must be a PSR-16 or PSR-6 cache, but the service "%s" is neither.', $cache));
        }
    }

    private function serviceClass(ContainerBuilder $container, string $id): ?string
    {
        $definition = $container->findDefinition($id);
        // Cache pools like cache.app are child definitions whose class comes from a parent.
        while (null === $definition->getClass() && $definition instanceof ChildDefinition) {
            $definition = $container->findDefinition($definition->getParent());
        }
        $class = $definition->getClass();
        if (null === $class) {
            return null;
        }
        $class = $container->getParameterBag()->resolveValue($class);

        return \is_string($class) && (class_exists($class) || interface_exists($class)) ? $class : null;
    }

    private function factory(ContainerBuilder $container, string $id, ?string $configured, string $interface, ?string $httpClientFactory): void
    {
        if (null !== $configured) {
            $container->setAlias($id, $configured);

            return;
        }
        if (null !== $httpClientFactory) {
            $container->setAlias($id, $httpClientFactory);

            return;
        }
        if ($container->has($interface)) {
            $container->setAlias($id, $interface);

            return;
        }
        if (!class_exists(Psr17Factory::class)) {
            throw new LogicException(sprintf('bannerstop_mention_api_client needs a %s. Install nyholm/psr7 or configure one.', $interface));
        }
        $container->register($id, Psr17Factory::class);
    }
}
