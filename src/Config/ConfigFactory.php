<?php

declare(strict_types=1);

namespace Bannerstop\MentionApiClientBundle\Config;

use Bannerstop\MentionApi\MentionApiConfig;

/**
 * Builds the MentionApiConfig of the client from the bundle configuration.
 *
 * @internal
 */
final class ConfigFactory
{
    /**
     * @param array{token_url?: string, client_id?: string, client_secret?: string, scope?: string} $clientCredentials
     */
    public static function create(string $baseUrl, float $timeout, ?string $apiKey, array $clientCredentials): MentionApiConfig
    {
        if (null !== $apiKey) {
            $config = MentionApiConfig::forApiKey($baseUrl, $apiKey);
        } else {
            $config = self::clientCredentials($baseUrl, $clientCredentials);
        }

        return $config->withTimeout($timeout);
    }

    /**
     * @param array{token_url?: string, client_id?: string, client_secret?: string, scope?: string} $clientCredentials
     */
    private static function clientCredentials(string $baseUrl, array $clientCredentials): MentionApiConfig
    {
        return MentionApiConfig::forClientCredentials(
            $baseUrl,
            (string) ($clientCredentials['token_url'] ?? ''),
            (string) ($clientCredentials['client_id'] ?? ''),
            (string) ($clientCredentials['client_secret'] ?? ''),
            (string) ($clientCredentials['scope'] ?? MentionApiConfig::DEFAULT_SCOPE)
        );
    }
}
