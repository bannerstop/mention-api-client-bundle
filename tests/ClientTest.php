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
}
