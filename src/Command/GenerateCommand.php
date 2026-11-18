<?php

declare(strict_types=1);

namespace Bannerstop\MentionApiClientBundle\Command;

use Bannerstop\MentionApi\Exception\MentionApiException;
use Bannerstop\MentionApi\Generator\Console\Application;
use Bannerstop\MentionApi\MentionApiClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Generates typed wrappers for the Queries and Routines of your Consumer, or checks that the committed ones are
 * current. It reads the OpenAPI document with the client of the application and hands it to the generator of
 * bannerstop/mention-api-client, so both write the same files.
 */
final class GenerateCommand extends Command
{
    public const NAME = 'mention-api:generate';

    /** @var MentionApiClient */
    private $client;
    /** @var string */
    private $projectDir;
    /** @var string|null */
    private $namespace;
    /** @var string|null */
    private $directory;

    public function __construct(MentionApiClient $client, string $projectDir, ?string $namespace, ?string $directory)
    {
        parent::__construct(self::NAME);
        $this->client = $client;
        $this->projectDir = $projectDir;
        $this->namespace = $namespace;
        $this->directory = $directory;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Generates typed wrappers for the Queries and Routines of your mention-api Consumer')
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'PHP namespace of the generated classes (default: generator.namespace of the bundle configuration)')
            ->addOption('dir', null, InputOption::VALUE_REQUIRED, 'Directory to write them to, relative to the project directory (default: generator.directory)')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Write nothing; exit with status 1 when the files differ from what would be generated')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Read the OpenAPI document from this file instead of fetching it')
            ->addOption('php', null, InputOption::VALUE_REQUIRED, 'The PHP the code is written for, 7.4 or 8.2 (default: decided by "require.php" of composer.json)')
            ->addOption('composer', null, InputOption::VALUE_REQUIRED, 'The composer.json to read the PHP version from (default: the one of the project)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stdout = static function (string $text) use ($output): void {
            $output->write($text, false, OutputInterface::OUTPUT_RAW);
        };
        $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $stderr = static function (string $text) use ($errors): void {
            $errors->write($text, false, OutputInterface::OUTPUT_RAW);
        };

        $namespace = $this->option($input, 'namespace') ?? $this->namespace;
        $directory = $this->option($input, 'dir') ?? $this->directory;
        $missing = [];
        if (null === $namespace || '' === $namespace) {
            $missing[] = 'the namespace (--namespace, or generator.namespace in the bundle configuration)';
        }
        if (null === $directory || '' === $directory) {
            $missing[] = 'the directory (--dir, or generator.directory in the bundle configuration)';
        }
        if ([] !== $missing) {
            $stderr('Missing ' . implode(' and ', $missing) . ".\n");

            return 2;
        }

        $args = ['generate', '--namespace=' . $namespace, '--dir=' . $directory];
        foreach (['php', 'composer'] as $name) {
            if (null !== $value = $this->option($input, $name)) {
                $args[] = '--' . $name . '=' . $value;
            }
        }
        if (true === $input->getOption('check')) {
            $args[] = '--check';
        }

        $temporary = null;
        try {
            $source = $this->option($input, 'source');
            if (null === $source) {
                try {
                    $document = $this->client->openApi();
                } catch (MentionApiException $e) {
                    $stderr('Error: the OpenAPI document could not be fetched: ' . $e->getMessage() . "\n");

                    return 2;
                }
                $file = tempnam(sys_get_temp_dir(), 'mention-api-');
                if (false === $file) {
                    $stderr("Error: cannot create a temporary file for the OpenAPI document.\n");

                    return 2;
                }
                $temporary = $file;
                if (false === file_put_contents($file, json_encode($document))) {
                    $stderr("Error: cannot write the OpenAPI document to a temporary file.\n");

                    return 2;
                }
                $source = $file;
            }
            $args[] = '--source=' . $source;

            return (new Application($stdout, $stderr))->run($args, $this->projectDir, []);
        } finally {
            if (null !== $temporary && is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function option(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
