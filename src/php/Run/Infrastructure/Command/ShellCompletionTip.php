<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Symfony\Component\Console\Output\OutputInterface;

use function basename;
use function getenv;
use function in_array;
use function is_string;
use function sprintf;

/**
 * Renders the optional "enable shell completion" tip shown at the end of
 * `phel init` / `phel agent-install`.
 *
 * Completion is opt-in: the tip surfaces the exact `phel completion <shell>`
 * command (tailored to the user's `$SHELL` when recognised) and points to the
 * README for the global-binary prerequisite. It never writes to the user's
 * shell config, which would require OS-specific paths and (for bash) root.
 */
final readonly class ShellCompletionTip
{
    /** @var list<string> */
    private const array SUPPORTED_SHELLS = ['bash', 'zsh', 'fish'];

    /**
     * Writes the tip, detecting the shell from the ambient `$SHELL`.
     */
    public static function writeTo(OutputInterface $output): void
    {
        foreach (self::lines(getenv('SHELL')) as $line) {
            $output->writeln($line);
        }
    }

    /**
     * @param false|string|null $shellEnv the raw `$SHELL` value (path to the
     *                                    login shell), or false/null when unset
     *
     * @return list<string> output lines, ready to write verbatim
     */
    public static function lines(string|false|null $shellEnv): array
    {
        $shell = self::detectShell($shellEnv);

        return [
            '',
            '<info>Enable shell completion (optional):</info>',
            sprintf('  <comment>phel completion %s</comment>', $shell ?? 'bash|zsh|fish'),
            '  See the README "shell completion" section for install paths and the global-binary note.',
        ];
    }

    private static function detectShell(string|false|null $shellEnv): ?string
    {
        if (!is_string($shellEnv) || $shellEnv === '') {
            return null;
        }

        $name = basename($shellEnv);

        return in_array($name, self::SUPPORTED_SHELLS, true) ? $name : null;
    }
}
