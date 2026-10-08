<?php

declare(strict_types=1);

namespace PhelTest\Integration\Console;

use Phel;
use Phel\Console\ConsoleFactory;
use Phel\Console\Infrastructure\Command\FrameworkCommands;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\LazyCommand;

use function array_map;
use function dirname;
use function explode;
use function file_get_contents;
use function in_array;
use function ksort;
use function preg_match;
use function preg_match_all;
use function sort;
use function sprintf;
use function strlen;
use function strpos;
use function substr;

/**
 * `docs/stability.md` freezes the commands and `api-daemon` methods that
 * `docs/cli-reference.md` lists (ADR 0022). These keep the lists and the code
 * together, so adding one fails until the reference names it.
 */
final class CliReferenceCoverageTest extends TestCase
{
    private const string REFERENCE = '/docs/cli-reference.md';

    public function test_the_command_table_lists_every_phel_command_with_its_aliases(): void
    {
        self::assertSame(
            $this->registeredCommands(),
            $this->documentedCommands(),
            'The "Commands" table in docs/cli-reference.md no longer matches the registered commands.',
        );
    }

    public function test_the_api_daemon_table_lists_every_dispatcher_method(): void
    {
        self::assertSame(
            $this->dispatcherMethods(),
            $this->documentedApiDaemonMethods(),
            'The `api-daemon` method table in docs/cli-reference.md no longer matches JsonRpcDispatcher.',
        );
    }

    /**
     * @return array<string, list<string>> command name to its aliases
     */
    private function registeredCommands(): array
    {
        Phel::bootstrap(__DIR__);
        $loader = new ConsoleFactory()->createCommandLoader();
        $framework = array_map(
            static fn(LazyCommand $command): string => (string) $command->getName(),
            new FrameworkCommands()->lazyCommands(),
        );

        $commands = [];
        foreach ($loader->getNames() as $name) {
            $command = $loader->get($name);
            $canonical = (string) $command->getName();
            if ($command->isHidden() || in_array($canonical, $framework, true)) {
                continue;
            }

            $aliases = $command->getAliases();
            sort($aliases);
            $commands[$canonical] = $aliases;
        }

        ksort($commands);

        return $commands;
    }

    /**
     * Rows of the table under `## Commands`: the first cell holds the name,
     * then its aliases, each in backticks.
     *
     * @return array<string, list<string>>
     */
    private function documentedCommands(): array
    {
        preg_match_all('/^\| ((?:`[^`]+` ?)+)\|/m', $this->section('## Commands'), $rows);

        $commands = [];
        foreach ($rows[1] as $cell) {
            preg_match_all('/`([^`]+)`/', $cell, $names);
            $aliases = $names[1];
            $name = (string) array_shift($aliases);
            sort($aliases);
            $commands[$name] = $aliases;
        }

        ksort($commands);

        return $commands;
    }

    /**
     * @return list<string>
     */
    private function dispatcherMethods(): array
    {
        $source = (string) file_get_contents(dirname(__DIR__, 4) . '/src/php/Api/Infrastructure/Daemon/JsonRpcDispatcher.php');
        preg_match('/function invoke\(.*?default =>/s', $source, $body);
        preg_match_all("/^\\s+'(\\w+)' =>/m", $body[0] ?? '', $methods);

        $names = $methods[1];
        sort($names);

        return $names;
    }

    /**
     * @return list<string>
     */
    private function documentedApiDaemonMethods(): array
    {
        preg_match_all('/^\| `(\w+)` \|/m', $this->section('### `api-daemon`'), $rows);

        $names = $rows[1];
        sort($names);

        return $names;
    }

    private function section(string $heading): string
    {
        $reference = (string) file_get_contents(dirname(__DIR__, 4) . self::REFERENCE);
        $start = strpos($reference, "\n" . $heading . "\n");
        self::assertNotFalse($start, sprintf('docs/cli-reference.md has no "%s" heading.', $heading));

        $rest = substr($reference, $start + 1 + strlen($heading));
        $level = explode(' ', $heading)[0];
        $end = strpos($rest, "\n" . $level . ' ');

        return $end === false ? $rest : substr($rest, 0, $end);
    }
}
