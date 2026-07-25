<?php

declare(strict_types=1);

namespace Phel\Filesystem\Domain;

interface DirectoryWritabilityCheckerInterface
{
    /**
     * Returns true if the directory is writable by the current process.
     *
     * Abstraction over PHP's is_writable() so callers can be mocked in tests.
     *
     * Impure by nature: the answer depends on filesystem state, so two calls
     * with the same argument may legitimately disagree (e.g. after a chmod).
     *
     * @phpstan-impure
     */
    public function isWritable(string $tempDir): bool;
}
