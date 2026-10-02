<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use function dirname;
use function getcwd;
use function preg_replace;
use function rtrim;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Catalog prose names files as `docs/...`, relative to the Phel checkout. In a
 * user project those files live under `vendor/phel-lang/phel-lang/docs/`, so
 * text printed to the user names them from where the command runs.
 *
 * @internal
 */
final class InstallDocsPath
{
    public static function rewrite(string $text, ?string $workingDir = null, ?string $installDir = null): string
    {
        $prefix = self::prefix(
            $workingDir ?? (string) getcwd(),
            $installDir ?? dirname(__DIR__, 5),
        );

        if ($prefix === '') {
            return $text;
        }

        return (string) preg_replace('~(?<![\w/.-])docs/~', $prefix . 'docs/', $text);
    }

    private static function prefix(string $workingDir, string $installDir): string
    {
        // The PHAR ships no docs, so there is no better path to print.
        if (str_starts_with($installDir, 'phar://')) {
            return '';
        }

        $workingDir = rtrim($workingDir, '/');
        $installDir = rtrim($installDir, '/');

        if ($installDir === $workingDir) {
            return '';
        }

        if (str_starts_with($installDir, $workingDir . '/')) {
            return substr($installDir, strlen($workingDir) + 1) . '/';
        }

        return $installDir . '/';
    }
}
