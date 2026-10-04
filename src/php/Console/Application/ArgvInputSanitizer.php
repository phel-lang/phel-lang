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

            $consumed = $this->consumeOption($argv, $i);
            if ($consumed === null) {
                break;
            }

            [$tokens, $count] = $consumed;
            array_push($result, ...$tokens);
            $i += $count;
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

    /**
     * Reads the option at `$argv[$i]` the way Symfony's `ArgvInput` does.
     *
     * @param list<string> $argv
     *
     * @return array{list<string>, int}|null the tokens to emit and how many argv tokens they replace,
     *                                       or null when the token is not an option of `run`
     */
    private function consumeOption(array $argv, int $i): ?array
    {
        $arg = $argv[$i];
        $isLong = str_starts_with($arg, '--');
        $matched = $isLong
            ? $this->matchLongOption(substr($arg, 2))
            : $this->matchShortOptions(substr($arg, 1));

        if ($matched === null) {
            return null;
        }

        [$option, $hasValue] = $matched;

        if (!$option->acceptValue() || $hasValue) {
            return [[$arg], 1];
        }

        if ($option->isValueRequired()) {
            $tokens = array_slice($argv, $i, 2);

            return [$tokens, count($tokens)];
        }

        $bare = '--' . $option->getName() . '=';
        $prefix = $isLong ? '-' : substr($arg, 0, -1);

        return [$prefix === '-' ? [$bare] : [$prefix, $bare], 1];
    }

    /**
     * @return array{InputOption, bool}|null the option and whether its value is attached
     */
    private function matchLongOption(string $body): ?array
    {
        $name = explode('=', $body, 2)[0];

        foreach ($this->definitions() as $definition) {
            if ($definition->hasOption($name)) {
                return [$definition->getOption($name), str_contains($body, '=')];
            }

            if ($definition->hasNegation($name)) {
                return [$definition->getOption(substr($name, strlen('no-'))), true];
            }
        }

        return null;
    }

    /**
     * A cluster such as `-vq` is valid when every letter is a declared shortcut.
     * An option that takes a value consumes the rest of the cluster as that
     * value, so only its last letter can leave the value to the next token.
     *
     * @return array{InputOption, bool}|null the last option read and whether its value is attached
     */
    private function matchShortOptions(string $letters): ?array
    {
        $last = strlen($letters) - 1;

        for ($i = 0; $i <= $last; ++$i) {
            $option = $this->findShortOption($letters[$i]);
            if (!$option instanceof InputOption) {
                return null;
            }

            if ($option->acceptValue() || $i === $last) {
                return [$option, $i < $last];
            }
        }

        return null;
    }

    private function findShortOption(string $letter): ?InputOption
    {
        foreach ($this->definitions() as $definition) {
            if ($definition->hasShortcut($letter)) {
                return $definition->getOptionForShortcut($letter);
            }
        }

        return null;
    }

    /**
     * @return list<InputDefinition>
     */
    private function definitions(): array
    {
        return [$this->runCommand->getDefinition(), $this->applicationDefinition];
    }
}
