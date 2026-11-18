<?php

declare(strict_types=1);

namespace Bannerstop\MentionApiClientBundle\Tests\App;

use Bannerstop\MentionApiClientBundle\BannerstopMentionApiClientBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpKernel\Kernel;

/**
 * A small application with the bundle, whose Symfony HTTP client is answered by FakeMentionApi
 * (service "fake_mention_api"); "app.psr18_client" is a PSR-18 client of the application.
 */
final class TestKernel extends Kernel
{
    private string $projectDir;
    /** @var array<string, mixed> */
    private array $bundleConfig;

    /**
     * @param array<string, mixed> $bundleConfig the bannerstop_mention_api_client configuration
     */
    public function __construct(array $bundleConfig, string $projectDir)
    {
        $this->bundleConfig = $bundleConfig;
        $this->projectDir = $projectDir;
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new BannerstopMentionApiClientBundle()];
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    public function getCacheDir(): string
    {
        return $this->projectDir . '/var/cache';
    }

    public function getLogDir(): string
    {
        return $this->projectDir . '/var/log';
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'test',
                'test' => true,
                'cache' => [
                    'app' => 'cache.adapter.array',
                    'pools' => ['mention.cache' => ['adapter' => 'cache.adapter.array']],
                ],
            ] + self::frameworkDefaults());
            $container->loadFromExtension('bannerstop_mention_api_client', $this->bundleConfig);
            $container->register('fake_mention_api', FakeMentionApi::class)->setPublic(true);
            $container->register('http_client', MockHttpClient::class)
                ->setArguments([new Reference('fake_mention_api')])
                ->setPublic(true);
            $container->register('app.psr18_client', Psr18Client::class)->setArguments([new Reference('http_client')]);
        });
    }

    /**
     * Options whose defaults change between Symfony versions, set so that a test run shows no deprecations of the
     * framework itself.
     *
     * @return array<string, mixed>
     */
    private static function frameworkDefaults(): array
    {
        if (self::VERSION_ID >= 70300 && self::VERSION_ID < 80000) {
            return ['property_info' => ['with_constructor_extractor' => true]];
        }
        if (self::VERSION_ID >= 60000 && self::VERSION_ID < 70000) {
            return [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
            ];
        }

        return [];
    }
}
