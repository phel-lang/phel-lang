<?php

declare(strict_types=1);

namespace PhelTest\Integration\Console;

use Phel;
use Phel\Console\ConsoleFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;

use function in_array;
use function str_contains;
use function stripos;
use function trim;

/**
 * Every Phel CLI command must carry a one-line description and a `--help`
 * block with at least one usage example (#2503).
 */
final class CommandHelpCoverageTest extends TestCase
{
    /** @var list<string> Symfony/Gacela built-ins and hidden helpers. */
    private const array SKIP = [
        'help', 'list', 'completion', '_test-worker', '_complete',
        'cache:warm', 'debug:container', 'debug:dependencies', 'debug:modules',
        'list:modules', 'profile:report', 'validate:config',
    ];

    public function test_every_command_has_description_and_example(): void
    {
        Phel::bootstrap(__DIR__);
        $application = new ConsoleFactory()->createConsoleBootstrap();

        $checked = 0;
        foreach ($application->all() as $command) {
            $name = (string) $command->getName();
            if (in_array($name, self::SKIP, true)) {
                continue;
            }

            $this->assertCommandDocumented($command, $name);
            ++$checked;
        }

        self::assertGreaterThan(15, $checked, 'expected to audit the full command surface');
    }

    private function assertCommandDocumented(Command $command, string $name): void
    {
        self::assertNotSame('', trim($command->getDescription()), $name . ' is missing a description');

        $help = trim($command->getHelp());
        self::assertNotSame('', $help, $name . ' is missing a --help block');
        self::assertTrue(
            str_contains($help, 'phel ') || stripos($help, 'example') !== false,
            $name . ' --help has no usage example',
        );
    }
}
