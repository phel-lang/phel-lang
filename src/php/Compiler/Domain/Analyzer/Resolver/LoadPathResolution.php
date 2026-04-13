<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Resolver;

/**
 * Outcome of resolving a `(load "...")` path argument.
 *
 * `filesystem`: a fully-resolved absolute path, ready for file_exists.
 * `classpathAbsolute`: a path to be searched at runtime against
 *                     `phel\repl/src-dirs`.
 */
final readonly class LoadPathResolution
{
    public const string MODE_FILESYSTEM = 'filesystem';

    public const string MODE_CLASSPATH_ABSOLUTE = 'classpath-absolute';

    private function __construct(
        public string $mode,
        public string $path,
    ) {}

    public static function filesystem(string $absolutePath): self
    {
        return new self(self::MODE_FILESYSTEM, $absolutePath);
    }

    public static function classpathAbsolute(string $relativePath): self
    {
        return new self(self::MODE_CLASSPATH_ABSOLUTE, $relativePath);
    }

    public function isClasspathAbsolute(): bool
    {
        return $this->mode === self::MODE_CLASSPATH_ABSOLUTE;
    }
}
