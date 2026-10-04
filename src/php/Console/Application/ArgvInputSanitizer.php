<?php

declare(strict_types=1);

namespace Phel\Console\Application;

use Closure;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

use function array_slice;
use function count;
use function explode;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * @internal
 */
final class ArgvInputSanitizer
{
    private ?InputDefinition $runDefinition = null;

    /**
     * @param Closure():InputDefinition $runDefinitionResolver the definition of the `run` command: the options
     *                                                         it declares are the ones recognized before the
     *                                                         command name, so a new option cannot drift from
     *                                                         this class. Resolved on first use, so only a
     *                                                         `run` invocation builds the command.
     */
    public function __construct(
        private readonly Closure $runDefinitionResolver,
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
        if (($argv[1] ?? null) !== 'run') {
            return $argv;
        }

        $argc = count($argv);
        $result = [$argv[0], 'run'];

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

    private function findRunOption(string $arg): ?InputOption
    {
        $definition = $this->runDefinition ??= ($this->runDefinitionResolver)();

        if (str_starts_with($arg, '--')) {
            $name = explode('=', substr($arg, 2), 2)[0];

            return $definition->hasOption($name) ? $definition->getOption($name) : null;
        }

        if (strlen($arg) === 2 && $arg[0] === '-' && $definition->hasShortcut($arg[1])) {
            return $definition->getOptionForShortcut($arg[1]);
        }

        return null;
    }
}
