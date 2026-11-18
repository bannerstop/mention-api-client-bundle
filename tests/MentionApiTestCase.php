<?php

declare(strict_types=1);

namespace Bannerstop\MentionApiClientBundle\Tests;

use Bannerstop\MentionApiClientBundle\Tests\App\FakeMentionApi;
use Bannerstop\MentionApiClientBundle\Tests\App\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class MentionApiTestCase extends TestCase
{
    protected const BASE_URL = 'https://mention-api.example.com';

    /** @var TestKernel|null */
    private $kernel;
    /** @var string */
    protected $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/mention-api-client-bundle-' . getmypid() . '-' . bin2hex(random_bytes(4));
        mkdir($this->projectDir);
    }

    protected function tearDown(): void
    {
        if (null !== $this->kernel) {
            $this->kernel->shutdown();
            $this->kernel = null;
        }
        $this->remove($this->projectDir);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    protected static function apiKeyConfig(array $overrides = []): array
    {
        return array_replace_recursive(['base_url' => self::BASE_URL, 'api_key' => 'the-api-key'], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    protected static function clientCredentialsConfig(array $overrides = []): array
    {
        return array_replace_recursive([
            'base_url' => self::BASE_URL,
            'client_credentials' => [
                'token_url' => 'https://sso.example.com/realms/example/protocol/openid-connect/token',
                'client_id' => 'my-app',
                'client_secret' => 'the-client-secret',
            ],
        ], $overrides);
    }

    /**
     * Boots a kernel with the given bundle configuration.
     *
     * @param array<string, mixed> $bundleConfig
     */
    protected function boot(array $bundleConfig): ContainerInterface
    {
        $this->kernel = new TestKernel($bundleConfig, $this->projectDir);
        $this->kernel->boot();

        return $this->kernel->getContainer();
    }

    protected function kernel(): TestKernel
    {
        \assert(null !== $this->kernel);

        return $this->kernel;
    }

    /**
     * The service container that also reaches private services.
     */
    protected function services(): ContainerInterface
    {
        $services = $this->kernel()->getContainer()->get('test.service_container');
        \assert($services instanceof ContainerInterface);

        return $services;
    }

    protected function fake(): FakeMentionApi
    {
        $fake = $this->kernel()->getContainer()->get('fake_mention_api');
        \assert($fake instanceof FakeMentionApi);

        return $fake;
    }

    private function remove(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            unlink($path);

            return;
        }
        foreach (glob($path . '/{,.}*', GLOB_BRACE) ?: [] as $child) {
            if ('.' !== basename($child) && '..' !== basename($child)) {
                $this->remove($child);
            }
        }
        if (is_dir($path)) {
            rmdir($path);
        }
    }
}
