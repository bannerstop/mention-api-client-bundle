<?php

declare(strict_types=1);

namespace Bannerstop\MentionApiClientBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class GenerateCommandTest extends MentionApiTestCase
{
    private function document(): string
    {
        return (string) file_get_contents(__DIR__ . '/Fixtures/openapi.json');
    }

    /**
     * @param array<string, mixed> $config
     */
    private function tester(array $config): CommandTester
    {
        $this->boot($config);
        copy(__DIR__ . '/Fixtures/composer-php74.json', $this->projectDir . '/composer.json');

        return new CommandTester((new Application($this->kernel()))->find('mention-api:generate'));
    }

    private function queueDocument(): void
    {
        $this->fake()->enqueueJson(json_decode($this->document(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testTheCommandIsRegisteredWithTheApplication(): void
    {
        $this->boot(self::apiKeyConfig());

        $command = (new Application($this->kernel()))->find('mention-api:generate');

        self::assertSame('mention-api:generate', $command->getName());
        self::assertNotSame('', $command->getDescription());
    }

    public function testItFetchesTheDocumentWithTheConfiguredClientAndWritesTheWrappers(): void
    {
        $tester = $this->tester(self::apiKeyConfig());
        $this->queueDocument();

        $status = $tester->execute(['--namespace' => 'App\Mention', '--dir' => 'src/Mention']);

        self::assertSame(0, $status, $tester->getDisplay());
        self::assertStringContainsString('Wrote', $tester->getDisplay());
        self::assertFileExists($this->projectDir . '/src/Mention/Queries.php');
        self::assertFileExists($this->projectDir . '/src/Mention/Routines.php');
        self::assertStringContainsString('namespace App\Mention;', (string) file_get_contents($this->projectDir . '/src/Mention/Queries.php'));
        $request = $this->fake()->requests[0];
        self::assertSame('GET', $request['method']);
        self::assertSame(self::BASE_URL . '/v2/openapi.json', $request['url']);
        self::assertSame(['Bearer the-api-key'], $request['headers']['authorization']);
    }

    public function testCheckPassesForCurrentFilesAndFailsOnceTheyDrift(): void
    {
        $tester = $this->tester(self::apiKeyConfig());
        $this->queueDocument();
        $tester->execute(['--namespace' => 'App\Mention', '--dir' => 'src/Mention']);

        $this->queueDocument();
        self::assertSame(0, $tester->execute(['--namespace' => 'App\Mention', '--dir' => 'src/Mention', '--check' => true]));
        self::assertStringContainsString('up to date', $tester->getDisplay());

        file_put_contents($this->projectDir . '/src/Mention/Queries.php', "<?php\n// edited by hand\n", FILE_APPEND);
        $this->queueDocument();
        self::assertSame(1, $tester->execute(['--namespace' => 'App\Mention', '--dir' => 'src/Mention', '--check' => true]));
        self::assertStringContainsString('Queries.php', $tester->getDisplay());
    }

    public function testNamespaceAndDirectoryDefaultToTheConfiguration(): void
    {
        $tester = $this->tester(self::apiKeyConfig(['generator' => ['namespace' => 'App\Remote', 'directory' => 'src/Remote']]));
        $this->queueDocument();

        $status = $tester->execute([]);

        self::assertSame(0, $status, $tester->getDisplay());
        self::assertStringContainsString('namespace App\Remote;', (string) file_get_contents($this->projectDir . '/src/Remote/Queries.php'));
    }

    public function testAMissingNamespaceIsExplained(): void
    {
        $tester = $this->tester(self::apiKeyConfig());

        $status = $tester->execute(['--dir' => 'src/Mention']);

        self::assertSame(2, $status);
        self::assertStringContainsString('--namespace', $tester->getDisplay());
        self::assertStringContainsString('generator.namespace', $tester->getDisplay());
        self::assertSame([], $this->fake()->requests, 'Nothing is fetched without a target.');
    }

    public function testADocumentFileCanBeUsedInsteadOfTheLiveOne(): void
    {
        $tester = $this->tester(self::apiKeyConfig());
        copy(__DIR__ . '/Fixtures/openapi.json', $this->projectDir . '/openapi.json');

        $status = $tester->execute(['--namespace' => 'App\Mention', '--dir' => 'src/Mention', '--source' => 'openapi.json']);

        self::assertSame(0, $status, $tester->getDisplay());
        self::assertSame([], $this->fake()->requests);
    }

    public function testThePhpTargetIsReadFromComposerJsonAndCanBeOverridden(): void
    {
        $tester = $this->tester(self::apiKeyConfig());
        $this->queueDocument();

        $tester->execute(['--namespace' => 'App\Mention', '--dir' => 'src/Mention']);
        $plain = (string) file_get_contents($this->projectDir . '/src/Mention/Query/ListWidgetsRow.php');
        $this->queueDocument();
        $tester->execute(['--namespace' => 'App\Mention', '--dir' => 'src/Modern', '--php' => '8.2']);
        $modern = (string) file_get_contents($this->projectDir . '/src/Modern/Query/ListWidgetsRow.php');

        self::assertStringNotContainsString('final readonly class', $plain);
        self::assertStringContainsString('final readonly class', $modern);
    }

    public function testAnErrorOfMentionApiIsReportedWithItsRequestId(): void
    {
        $tester = $this->tester(self::apiKeyConfig());
        $this->fake()->enqueueProblem(401, 'unauthorized', 'req_denied');

        $status = $tester->execute(['--namespace' => 'App\Mention', '--dir' => 'src/Mention']);

        self::assertSame(2, $status);
        self::assertStringContainsString('req_denied', $tester->getDisplay());
        self::assertFileDoesNotExist($this->projectDir . '/src/Mention/Queries.php');
    }
}
