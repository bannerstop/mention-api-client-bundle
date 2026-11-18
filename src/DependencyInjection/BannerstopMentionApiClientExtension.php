<?php

declare(strict_types=1);

namespace Bannerstop\MentionApiClientBundle\DependencyInjection;

use Bannerstop\MentionApi\MentionApiClient;
use Bannerstop\MentionApi\MentionApiConfig;
use Bannerstop\MentionApiClientBundle\Command\GenerateCommand;
use Bannerstop\MentionApiClientBundle\Config\ConfigFactory;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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
        $this->validate($config, $container);

        $container->setParameter('bannerstop_mention_api_client.psr', [
            'http_client' => $config['http_client'],
            'request_factory' => $config['request_factory'],
            'stream_factory' => $config['stream_factory'],
            'cache' => $config['cache'],
        ]);

        $container->setParameter('bannerstop_mention_api_client.timeout', $config['timeout']);

        $container->register(self::CONFIG, MentionApiConfig::class)
            ->setFactory([ConfigFactory::class, 'create'])
            ->setArguments([$config['base_url'], '%bannerstop_mention_api_client.timeout%', $config['api_key'], $config['client_credentials'] ?? []]);

        $container->register(MentionApiClient::class, MentionApiClient::class)
            ->setArguments([
                new Reference(self::CONFIG),
                new Reference(self::HTTP_CLIENT),
                new Reference(self::REQUEST_FACTORY),
                new Reference(self::STREAM_FACTORY),
                new Reference(self::CACHE, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true);

        $container->register('bannerstop_mention_api_client.generate_command', GenerateCommand::class)
            ->setArguments([
                new Reference(MentionApiClient::class),
                '%kernel.project_dir%',
                $config['generator']['namespace'],
                $config['generator']['directory'],
            ])
            ->addTag('console.command', ['command' => GenerateCommand::NAME, 'description' => 'Generates typed wrappers for the Queries and Routines of your mention-api Consumer']);
    }

    /**
     * Checks what the configuration tree cannot: values that are environment variables are only known at runtime and
     * are left to the runtime checks of the core package.
     *
     * @param array<string, mixed> $config
     */
    private function validate(array $config, ContainerBuilder $container): void
    {
        $this->assertUrl($container, $config['base_url'], 'base_url');
        if (\is_string($config['base_url']) && !$this->isEnvironmentValue($container, $config['base_url']) && 1 === preg_match('#/v2/?$#', $config['base_url'])) {
            throw new InvalidConfigurationException('bannerstop_mention_api_client.base_url must be the address of mention-api without /v2, e.g. https://mention-api.example.com: the client adds the path.');
        }
        if (isset($config['client_credentials']['token_url'])) {
            $this->assertUrl($container, $config['client_credentials']['token_url'], 'client_credentials.token_url');
        }
        if (is_numeric($config['timeout']) && $config['timeout'] <= 0) {
            throw new InvalidConfigurationException('bannerstop_mention_api_client.timeout must be greater than zero (seconds).');
        }
    }

    /**
     * @param mixed $value
     */
    private function assertUrl(ContainerBuilder $container, $value, string $option): void
    {
        if (!\is_string($value) || $this->isEnvironmentValue($container, $value)) {
            return;
        }
        if (1 !== preg_match('#^https?://[^/\s]#i', $value)) {
            throw new InvalidConfigurationException(sprintf('bannerstop_mention_api_client.%s must be an http(s) URL such as https://mention-api.example.com, got "%s".', $option, $value));
        }
    }

    private function isEnvironmentValue(ContainerBuilder $container, string $value): bool
    {
        $usedEnvironmentVariables = [];
        $container->resolveEnvPlaceholders($value, null, $usedEnvironmentVariables);

        return [] !== $usedEnvironmentVariables;
    }

    public function getAlias(): string
    {
        return 'bannerstop_mention_api_client';
    }
}
