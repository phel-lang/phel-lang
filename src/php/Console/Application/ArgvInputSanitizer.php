<?php

declare(strict_types=1);

namespace Phel\Console\Application;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

use function array_filter;
use function array_slice;
use function array_values;
use function count;
use function explode;
use function in_array;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * @internal
 */
final readonly class ArgvInputSanitizer
{
    /**
     * @param Command         $runCommand            the `run` command: its name, aliases and declared options
     *                                               are what gets recognized, so none of them can drift from
     *                                               this class
     * @param InputDefinition $applicationDefinition the options every command accepts (`-v`, `-q`, `--no-ansi`, ...)
     */
    public function __construct(
        private Command $runCommand,
        private InputDefinition $applicationDefinition,
    ) {}

    /**
     * Normalizes `phel run` invocations so options/command/args are well-structured.
     *
     * Options understood by `phel run` itself, and `--warn-deprecations`, are
     * recognized only while they appear before the command name. Once the
     * command token is reached, every remaining argument is forwarded verbatim
     * after a `--` separator, so script options stay distinct from arguments
     * meant for the user command.
     *
     * An option with an optional value is rewritten to `--name=` when given
     * bare: Symfony would otherwise read the command token that follows as its
     * value.
     *
     * Examples:
     *   phel run -t cmd arg1 arg2    => [script, run, -t, cmd, --, arg1, arg2]
     *   phel run --with-time         => [script, run, --with-time]
     *   phel run --debug app.phel    => [script, run, --debug=, app.phel]
     *
     * @param list<string> $argv
     *
     * @return list<string>
     */
    public function sanitize(array $argv): array
    {
        // Nothing to do if this isn't a `run` invocation (or argv too short).
        if (!in_array($argv[1] ?? null, $this->runNames(), true)) {
            return $argv;
        }

        $argc = count($argv);
        $result = array_slice($argv, 0, 2);

        $i = 2;

        while ($i < $argc) {
            if (WarnDeprecationsFlag::matches($argv[$i])) {
                $result[] = $argv[$i];
                ++$i;
                continue;
            }

            $option = $this->findRunOption($argv[$i]);
            if (!$option instanceof InputOption) {
                break;
            }

            if (!$option->acceptValue() || str_contains($argv[$i], '=')) {
                $result[] = $argv[$i];
                ++$i;
            } elseif ($option->isValueRequired()) {
                array_push($result, ...array_slice($argv, $i, 2));
                $i += 2;
            } else {
                $result[] = '--' . $option->getName() . '=';
                ++$i;
            }
        }

        if ($i < $argc) {
            $result[] = $argv[$i];
            ++$i;
        }

        if ($i < $argc) {
            $result[] = '--';
            /** @var list<string> $rest */
            $rest = array_slice($argv, $i);
            array_push($result, ...$rest);
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function runNames(): array
    {
        return array_values(array_filter([$this->runCommand->getName(), ...$this->runCommand->getAliases()]));
    }

    private function findRunOption(string $arg): ?InputOption
    {
        foreach ([$this->runCommand->getDefinition(), $this->applicationDefinition] as $definition) {
            $option = $this->findOption($definition, $arg);
            if ($option instanceof InputOption) {
                return $option;
            }
        }

        return null;
    }

    private function findOption(InputDefinition $definition, string $arg): ?InputOption
    {
        if (str_starts_with($arg, '--')) {
            $name = explode('=', substr($arg, 2), 2)[0];

            if ($definition->hasOption($name)) {
                return $definition->getOption($name);
            }

            return $definition->hasNegation($name)
                ? $definition->getOption(substr($name, strlen('no-')))
                : null;
        }

        $shortcut = substr($arg, 1);
        if (str_starts_with($arg, '-') && $shortcut !== '' && $definition->hasShortcut($shortcut)) {
            return $definition->getOptionForShortcut($shortcut);
        }

        return null;
    }
}
