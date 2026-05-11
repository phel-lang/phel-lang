<?php

declare(strict_types=1);

namespace Phel\Filesystem\Application;

use function is_string;
use function strlen;

use const DIRECTORY_SEPARATOR;
use const LOCK_EX;

/**
 * Owns the per-project `.phel/` directory: creates it on demand, seeds a
 * `.gitignore` so consumers never see the directory in `git status`, and
 * resolves the effective location (overridable via `PHEL_DIR` env var or
 * `PhelConfig::setPhelDir()`). Pattern borrowed from pytest / mypy / ruff.
 *
 * Best-effort: never throws. On read-only filesystems or sandboxed runners
 * the helper silently no-ops and returns the path it would have created;
 * downstream writes surface the real OS error at their actual call site.
 */
final class PhelProjectDirectory
{
    public const string DIRECTORY_NAME = '.phel';

    public const string DIR_ENV = 'PHEL_DIR';

    private const string GITIGNORE_FILENAME = '.gitignore';

    private const string GITIGNORE_CONTENT = "# Created automatically by Phel.\n*\n";

    /**
     * Best-effort: creates the resolved state directory if missing and seeds
     * `.gitignore` once. Returns the path even when creation failed.
     */
    public static function ensure(string $projectRoot): string
    {
        $dir = self::path($projectRoot);

        if (!is_dir($dir)) {
            @mkdir($dir, 0o755, true);
        }

        $gitignore = $dir . DIRECTORY_SEPARATOR . self::GITIGNORE_FILENAME;
        if (is_dir($dir) && !file_exists($gitignore)) {
            @file_put_contents($gitignore, self::GITIGNORE_CONTENT, LOCK_EX);
        }

        return $dir;
    }

    /**
     * Absolute path to `<resolvedDir>/<subpath>`. Resolution order:
     * `PHEL_DIR` env var → caller-provided override → `<projectRoot>/.phel`.
     * Subpath segments are joined with `/`; an empty subpath returns the
     * directory itself.
     */
    public static function path(string $projectRoot, string $subpath = '', string $configuredDir = ''): string
    {
        $base = self::resolveBase($projectRoot, $configuredDir);

        return $subpath === ''
            ? $base
            : $base . DIRECTORY_SEPARATOR . ltrim($subpath, '/\\');
    }

    /**
     * Resolve a config-supplied path string against the project root,
     * rewriting `.phel/...` prefixes through the active state directory.
     * Used by `BuildConfig::getCacheDir` and `CommandConfig::getErrorLogFile`
     * so `PHEL_DIR` / `setPhelDir()` relocates derived paths automatically.
     */
    public static function resolve(string $projectRoot, string $configPath, string $configuredDir = ''): string
    {
        if ($configPath === '') {
            return '';
        }

        if (self::isAbsolutePath($configPath)) {
            return $configPath;
        }

        $prefix = self::DIRECTORY_NAME . DIRECTORY_SEPARATOR;
        if (str_starts_with($configPath, $prefix) || str_starts_with($configPath, self::DIRECTORY_NAME . '/')) {
            $sub = substr($configPath, strlen(self::DIRECTORY_NAME) + 1);
            return self::path($projectRoot, $sub, $configuredDir);
        }

        if ($configPath === self::DIRECTORY_NAME) {
            return self::path($projectRoot, '', $configuredDir);
        }

        return rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . $configPath;
    }

    private static function resolveBase(string $projectRoot, string $configuredDir): string
    {
        $env = getenv(self::DIR_ENV);
        $candidate = is_string($env) && $env !== ''
            ? $env
            : ($configuredDir !== '' ? $configuredDir : self::DIRECTORY_NAME);

        if (self::isAbsolutePath($candidate)) {
            return rtrim($candidate, '/\\');
        }

        return rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . $candidate;
    }

    private static function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        if (str_starts_with($path, 'phar://')) {
            return true;
        }

        return (bool) preg_match('~^[A-Za-z]:[\\\\/]~', $path);
    }
}
