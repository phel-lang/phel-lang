<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Resolver;

/**
 * Outcome of resolving a `(load "...")` path argument.
 *
 * Both `loadKey` and `callerClasspathDir` are portable, classpath-style
 * paths — never absolute filesystem paths from the build machine. This
 * lets the emitter produce output that can be shipped and re-resolved
 * against whatever classpath roots exist at runtime.
 *
 * - `loadKey`: the path argument with no extension, normalized. For a
 *   classpath-absolute load (`(load "/foo/bar")`) this is the full key;
 *   for a caller-relative load (`(load "core/util")`) this is the
 *   sibling key under the caller's classpath directory.
 * - `callerClasspathDir`: the caller namespace's classpath directory —
 *   i.e. the directory that holds the caller when its namespace is
 *   laid out on the classpath. Empty for classpath-absolute loads.
 * - `isClasspathAbsolute`: true when the argument started with `/` and
 *   the lookup must search classpath roots without the caller prefix.
 */
final readonly class LoadPathResolution
{
    private function __construct(
        public string $loadKey,
        public string $callerClasspathDir,
        public bool $isClasspathAbsolute,
    ) {}

    public static function callerRelative(string $loadKey, string $callerClasspathDir): self
    {
        return new self($loadKey, $callerClasspathDir, isClasspathAbsolute: false);
    }

    public static function classpathAbsolute(string $loadKey): self
    {
        return new self($loadKey, '', isClasspathAbsolute: true);
    }

    public function isClasspathAbsolute(): bool
    {
        return $this->isClasspathAbsolute;
    }
}
