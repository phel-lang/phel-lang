<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Diagnostic;

use function getcwd;
use function preg_quote;
use function preg_replace;
use function realpath;
use function rtrim;

/**
 * Shortens the absolute paths in a diagnostic to the spelling a user types
 * from where they ran the command: `src/main.phel` for a file under the
 * working directory, the absolute path for anything else.
 *
 * Applied when the text is printed, not when it is built: a recorded notice
 * keeps its absolute path, so a warm run replaying it from another directory
 * still renders it against that directory.
 *
 * @internal
 */
final class WorkingDirectoryPaths
{
    public static function shorten(string $text): string
    {
        $cwd = getcwd();
        if ($cwd === false) {
            return $text;
        }

        // Both spellings of the directory: a path resolved through a symlink
        // (macOS `/tmp` is `/private/tmp`) names it by its real path.
        foreach ([$cwd, realpath($cwd)] as $directory) {
            if ($directory === false || rtrim($directory, '/') === '') {
                continue;
            }

            // Only where a path starts, so `/other/abs/app/x` keeps its prefix
            // when the working directory is `/abs/app`.
            $prefix = preg_quote(rtrim($directory, '/') . '/', '~');
            $text = (string) preg_replace('~(^|[\s(\'"])' . $prefix . '~', '$1', $text);
        }

        return $text;
    }
}
