<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Resolver;

use InvalidArgumentException;

use function dirname;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function substr;

/**
 * Resolves a `(load "...")` path argument into either an absolute
 * filesystem path (for caller-relative loads) or a classpath-relative
 * path to be searched at runtime against `phel\repl/src-dirs`.
 *
 * Mirrors Clojure's `clojure.core/load`: a leading slash marks a
 * classpath-absolute path; anything else is resolved relative to the
 * caller. Phel differs from Clojure in one detail — the caller base is
 * the caller file's directory (compile-time frozen), not a namespace
 * package directory, because Phel does not require filesystem layout
 * to match namespace layout.
 */
final class LoadPathResolver
{
    private const string EXTENSION = '.phel';

    public function resolve(?string $callerFile, string $pathArg): LoadPathResolution
    {
        $this->rejectInvalidPathArg($pathArg);

        if ($this->isClasspathAbsolute($pathArg)) {
            $relative = substr($pathArg, 1) . self::EXTENSION;

            return LoadPathResolution::classpathAbsolute($relative);
        }

        if ($callerFile === null || $callerFile === '') {
            throw new InvalidArgumentException(sprintf(
                "'load cannot resolve relative path '%s' — no caller source location available",
                $pathArg,
            ));
        }

        return LoadPathResolution::filesystem(dirname($callerFile) . '/' . $pathArg . self::EXTENSION);
    }

    private function rejectInvalidPathArg(string $pathArg): void
    {
        if ($pathArg === '') {
            throw new InvalidArgumentException("'load path must not be empty");
        }

        if (str_ends_with($pathArg, self::EXTENSION)) {
            throw new InvalidArgumentException(sprintf(
                "'load path must not include the '%s' extension, got: %s",
                self::EXTENSION,
                $pathArg,
            ));
        }

        if (str_starts_with($pathArg, './') || str_starts_with($pathArg, '../')) {
            throw new InvalidArgumentException(sprintf(
                "'load path must not start with './' or '../'; use a relative or classpath-absolute path, got: %s",
                $pathArg,
            ));
        }
    }

    private function isClasspathAbsolute(string $pathArg): bool
    {
        return str_starts_with($pathArg, '/');
    }
}
