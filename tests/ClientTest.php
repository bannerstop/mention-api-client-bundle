<?php

declare(strict_types=1);

namespace Bannerstop\MentionApiClientBundle\Tests;

use Bannerstop\MentionApi\MentionApiClient;

final class ClientTest extends MentionApiTestCase
{
    public function testTheClientIsAutowirableAndCallsTheConfiguredBaseUrlWithTheApiKey(): void
    {
        $container = $this->boot(self::apiKeyConfig());
        $this->fake()->enqueueRows([['id' => 1]]);

        $client = $container->get(MentionApiClient::class);
        self::assertInstanceOf(MentionApiClient::class, $client);
        $result = $client->run('getThings');

        self::assertSame([['id' => 1]], $result->getRows());
        $request = $this->fake()->requests[0];
        self::assertSame('POST', $request['method']);
        self::assertStringStartsWith(self::BASE_URL . '/v2/', $request['url']);
        self::assertSame(['Bearer the-api-key'], $request['headers']['authorization']);
    }

    public function testClientCredentialsFetchATokenThatIsKeptInTheConfiguredCachePool(): void
    {
        $container = $this->boot(self::clientCredentialsConfig(['cache' => 'mention.cache']));
        $this->fake()
            ->enqueueJson(['access_token' => 'token-1', 'expires_in' => 300, 'token_type' => 'Bearer'])
            ->enqueueRows([])
            ->enqueueRows([]);

        $client = $container->get(MentionApiClient::class);
        $client->run('getThings');
        $client->run('getThings');

        $requests = $this->fake()->requests;
        self::assertCount(3, $requests, 'One token request, then the two calls.');
        self::assertSame('https://sso.example.com/realms/example/protocol/openid-connect/token', $requests[0]['url']);
        parse_str($requests[0]['body'], $form);
        self::assertSame(['grant_type' => 'client_credentials', 'client_id' => 'my-app', 'client_secret' => 'the-client-secret', 'scope' => 'mention-api'], $form);
        self::assertSame(['Bearer token-1'], $requests[2]['headers']['authorization']);
        $pool = $this->services()->get('mention.cache');
        self::assertNotSame([], $pool->getValues(), 'The token is stored in the configured pool.');
    }

    public function testTheTokenGoesToTheApplicationCacheByDefault(): void
    {
        $container = $this->boot(self::clientCredentialsConfig());
        $this->fake()->enqueueJson(['access_token' => 'token-1', 'expires_in' => 300])->enqueueRows([]);

        $container->get(MentionApiClient::class)->run('getThings');

        self::assertNotSame([], $this->services()->get('cache.app')->getValues());
    }

    public function testTheCacheCanBeSwitchedOff(): void
    {
        $container = $this->boot(self::clientCredentialsConfig(['cache' => null]));
        $this->fake()->enqueueJson(['access_token' => 'token-1', 'expires_in' => 300])->enqueueRows([]);

        $container->get(MentionApiClient::class)->run('getThings');

        self::assertSame([], $this->services()->get('cache.app')->getValues());
    }

    public function testTheConfiguredTimeoutReachesTheHttpClient(): void
    {
        $container = $this->boot(self::apiKeyConfig(['timeout' => 7.5]));
        $this->fake()->enqueueRows([]);

        $container->get(MentionApiClient::class)->run('getThings');

        $options = $this->fake()->requests[0]['options'];
        self::assertEqualsWithDelta(7.5, $options['timeout'], 0.001);
        self::assertEqualsWithDelta(7.5, $options['max_duration'], 0.001);
    }

    public function testTheTimeoutIs30SecondsByDefault(): void
    {
        $container = $this->boot(self::apiKeyConfig());
        $this->fake()->enqueueRows([]);

        $container->get(MentionApiClient::class)->run('getThings');

        self::assertEqualsWithDelta(30.0, $this->fake()->requests[0]['options']['timeout'], 0.001);
    }
}
