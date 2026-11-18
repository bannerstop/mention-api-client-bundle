<?php

declare(strict_types=1);

namespace Bannerstop\MentionApiClientBundle\Tests;

use Bannerstop\MentionApi\MentionApiClient;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Exception\LogicException;

final class ConfigurationTest extends MentionApiTestCase
{
    /**
     * @param array<string, mixed> $config
     */
    private function assertRejected(array $config, string $message, string $exception = InvalidConfigurationException::class): void
    {
        try {
            $this->boot($config);
        } catch (\Exception $e) {
            self::assertInstanceOf($exception, $e);
            self::assertStringContainsString($message, $e->getMessage());

            return;
        }
        self::fail('The configuration was accepted, but it should be rejected with: ' . $message);
    }

    public function testACredentialIsRequired(): void
    {
        $this->assertRejected(['base_url' => self::BASE_URL], 'Configure api_key or client_credentials');
    }

    public function testApiKeyAndClientCredentialsExcludeEachOther(): void
    {
        $this->assertRejected(self::clientCredentialsConfig(['api_key' => 'the-api-key']), 'not both');
    }

    public function testTheBaseUrlIsRequired(): void
    {
        $this->assertRejected(['api_key' => 'the-api-key'], 'The child config "base_url" under "bannerstop_mention_api_client" must be configured');
    }

    public function testTheBaseUrlMustBeAnHttpUrl(): void
    {
        $this->assertRejected(self::apiKeyConfig(['base_url' => 'mention-api.example.com']), 'base_url must be an http(s) URL');
    }

    public function testTheBaseUrlMustNotContainTheVersionPath(): void
    {
        $this->assertRejected(self::apiKeyConfig(['base_url' => 'https://mention-api.example.com/v2']), 'without /v2');
    }

    public function testTheTokenUrlMustBeAnHttpUrl(): void
    {
        $this->assertRejected(self::clientCredentialsConfig(['client_credentials' => ['token_url' => 'sso']]), 'token_url must be an http(s) URL');
    }

    public function testClientCredentialsNeedAllTheirParts(): void
    {
        $config = self::clientCredentialsConfig();
        unset($config['client_credentials']['client_secret']);

        $this->assertRejected($config, 'The child config "client_secret" under "bannerstop_mention_api_client.client_credentials" must be configured');
    }

    public function testTheTimeoutMustBePositive(): void
    {
        $this->assertRejected(self::apiKeyConfig(['timeout' => 0]), 'timeout must be greater than zero');
    }

    public function testAnUnknownHttpClientServiceIsNamed(): void
    {
        $this->assertRejected(self::apiKeyConfig(['http_client' => 'app.missing_client']), 'refers to the service "app.missing_client", which does not exist', LogicException::class);
    }

    public function testAnUnknownCacheServiceIsNamed(): void
    {
        $this->assertRejected(self::apiKeyConfig(['cache' => 'app.missing_cache']), 'refers to the service "app.missing_cache", which does not exist', LogicException::class);
    }

    public function testSettingsMayComeFromEnvironmentVariables(): void
    {
        $_SERVER['MENTION_API_TEST_URL'] = self::BASE_URL;
        $_SERVER['MENTION_API_TEST_KEY'] = 'key-from-env';
        $_SERVER['MENTION_API_TEST_TIMEOUT'] = '5';
        try {
            $container = $this->boot([
                'base_url' => '%env(MENTION_API_TEST_URL)%',
                'api_key' => '%env(MENTION_API_TEST_KEY)%',
                'timeout' => '%env(float:MENTION_API_TEST_TIMEOUT)%',
            ]);
            $this->fake()->enqueueRows([]);

            $container->get(MentionApiClient::class)->run('getThings');
        } finally {
            unset($_SERVER['MENTION_API_TEST_URL'], $_SERVER['MENTION_API_TEST_KEY'], $_SERVER['MENTION_API_TEST_TIMEOUT']);
        }

        $request = $this->fake()->requests[0];
        self::assertStringStartsWith(self::BASE_URL . '/v2/', $request['url']);
        self::assertSame(['Bearer key-from-env'], $request['headers']['authorization']);
        self::assertEqualsWithDelta(5.0, $request['options']['timeout'], 0.001);
    }
}
