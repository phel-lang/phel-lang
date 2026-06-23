<?php

declare(strict_types=1);

namespace Phel\Config;

use Error;
use RuntimeException;
use Throwable;

use function is_string;
use function realpath;
use function sprintf;
use function str_contains;

/**
 * Thrown when a project's `phel-config.php` cannot be loaded: it has a syntax
 * error, raises an error while evaluating, or does not `return` a usable
 * config value.
 *
 * Replaces the cryptic underlying failure (e.g. Gacela's "The PHP config file
 * must return an array or a JsonSerializable object!") with a message that
 * names the file and shows the expected shape.
 */
final class ConfigLoadException extends RuntimeException
{
    /**
     * Wraps $error as a {@see ConfigLoadException} when it represents a failure
     * to load $configPath; otherwise returns $error unchanged, so unrelated
     * bootstrap failures keep their original type and message.
     */
    public static function wrapIfConfigError(Throwable $error, string $configPath): Throwable
    {
        if (!self::isConfigLoadError($error, $configPath)) {
            return $error;
        }

        return new self(
            sprintf(
                "Failed to load %s: %s\n\n"
                . "A phel-config.php must `return` a PhelConfig instance, for example:\n\n"
                . "    <?php\n"
                . "    declare(strict_types=1);\n"
                . "    use Phel\\Config\\PhelConfig;\n"
                . "    return new PhelConfig()->withSrcDirs(['src']);",
                $configPath,
                $error->getMessage(),
            ),
            previous: $error,
        );
    }

    private static function isConfigLoadError(Throwable $error, string $configPath): bool
    {
        if (realpath($configPath) === false) {
            return false;
        }

        // Gacela's reader rejects a non-array / non-JsonSerializable return.
        if (str_contains($error->getMessage(), 'must return an array or a JsonSerializable')) {
            return true;
        }

        // A PHP error (parse/type/...) raised while evaluating the config file.
        return $error instanceof Error && self::originatesFrom($error, $configPath);
    }

    private static function originatesFrom(Throwable $error, string $configPath): bool
    {
        $target = realpath($configPath);
        if ($target === false) {
            return false;
        }

        if (realpath($error->getFile()) === $target) {
            return true;
        }

        foreach ($error->getTrace() as $frame) {
            $file = $frame['file'] ?? null;
            if (is_string($file) && realpath($file) === $target) {
                return true;
            }
        }

        return false;
    }
}
