<?php

declare(strict_types=1);

namespace Bannerstop\MentionApiClientBundle\DependencyInjection;

use Bannerstop\MentionApi\MentionApiClient;
use Bannerstop\MentionApi\MentionApiConfig;
use Bannerstop\MentionApiClientBundle\Config\ConfigFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The service to inject is Bannerstop\MentionApi\MentionApiClient.
 */
final class BannerstopMentionApiClientExtension extends Extension
{
    public const CONFIG = 'bannerstop_mention_api_client.config';
    public const HTTP_CLIENT = 'bannerstop_mention_api_client.http_client';
    public const REQUEST_FACTORY = 'bannerstop_mention_api_client.request_factory';
    public const STREAM_FACTORY = 'bannerstop_mention_api_client.stream_factory';
    public const CACHE = 'bannerstop_mention_api_client.cache';

    /**
     * @param array<mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('bannerstop_mention_api_client.psr', [
            'http_client' => $config['http_client'],
            'request_factory' => $config['request_factory'],
            'stream_factory' => $config['stream_factory'],
        ]);

        $container->register(self::CONFIG, MentionApiConfig::class)
            ->setFactory([ConfigFactory::class, 'create'])
            ->setArguments([$config['base_url'], $config['api_key'], $config['client_credentials'] ?? []]);

        $container->register(MentionApiClient::class, MentionApiClient::class)
            ->setArguments([
                new Reference(self::CONFIG),
                new Reference(self::HTTP_CLIENT),
                new Reference(self::REQUEST_FACTORY),
                new Reference(self::STREAM_FACTORY),
                new Reference(self::CACHE, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true);
    }

    public function getAlias(): string
    {
        return 'bannerstop_mention_api_client';
    }
}
