<?php

declare(strict_types=1);

namespace Bannerstop\MentionApiClientBundle\DependencyInjection;

use Bannerstop\MentionApi\MentionApiConfig;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('bannerstop_mention_api_client');
        $root = $treeBuilder->getRootNode();
        \assert($root instanceof ArrayNodeDefinition);

        $root
            ->children()
                ->scalarNode('base_url')->isRequired()->cannotBeEmpty()->info('Base URL of mention-api without /v2, e.g. https://mention-api.example.com')->end()
                ->floatNode('timeout')->defaultValue(MentionApiConfig::DEFAULT_TIMEOUT)->info('Seconds to wait for an answer, also for the whole call with the default HTTP client')->end()
                ->scalarNode('api_key')->defaultNull()->info('The API key of your Consumer; use this or client_credentials')->end()
                ->arrayNode('client_credentials')
                    ->info('Authenticate with the OAuth 2.0 client-credentials grant; use this or api_key')
                    ->children()
                        ->scalarNode('token_url')->isRequired()->cannotBeEmpty()->info('e.g. https://sso.example.com/realms/example/protocol/openid-connect/token')->end()
                        ->scalarNode('client_id')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('client_secret')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('scope')->defaultValue(MentionApiConfig::DEFAULT_SCOPE)->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->scalarNode('cache')->defaultValue('cache.app')->info('Service id of a PSR-16 or PSR-6 cache that keeps the access token of client_credentials, null to disable')->end()
                ->arrayNode('generator')
                    ->info('Defaults for the mention-api:generate command')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('namespace')->defaultNull()->info('PHP namespace of the generated classes, e.g. App\\Mention')->end()
                        ->scalarNode('directory')->defaultNull()->info('Directory they are written to, relative to the project directory, e.g. src/Mention')->end()
                    ->end()
                ->end()
                ->scalarNode('http_client')->defaultNull()->info('Service id of a PSR-18 client; defaults to the Symfony HTTP client ("http_client")')->end()
                ->scalarNode('request_factory')->defaultNull()->info('Service id of a PSR-17 request factory; defaults to the HTTP client if it is one, else nyholm/psr7')->end()
                ->scalarNode('stream_factory')->defaultNull()->info('Service id of a PSR-17 stream factory; same default as request_factory')->end()
            ->end()
            ->validate()
                ->ifTrue(static function (array $config): bool {
                    return null === $config['api_key'] && [] === ($config['client_credentials'] ?? []);
                })
                ->thenInvalid('Configure api_key or client_credentials: mention-api needs a credential.')
            ->end()
            ->validate()
                ->ifTrue(static function (array $config): bool {
                    return null !== $config['api_key'] && [] !== ($config['client_credentials'] ?? []);
                })
                ->thenInvalid('Configure either api_key or client_credentials, not both.')
            ->end();

        return $treeBuilder;
    }
}
