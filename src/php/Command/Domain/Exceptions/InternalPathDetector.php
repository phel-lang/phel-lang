<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Exceptions;

use function str_starts_with;

/**
 * Tells Phel's own machinery apart from the user's project when reporting an
 * error: the bundled core library and runtime (`<phel>/src/…`), the eval temp
 * file and everything under the compiled cache.
 *
 * @internal
 */
final readonly class InternalPathDetector
{
    public function __construct(
        private string $phelInternalSrcDir,
        private string $cacheDir,
    ) {}

    /**
     * Whether the source-mapped file belongs to Phel itself rather than to the
     * user's project, and is therefore no place for the user to act on.
     */
    public function isInternalSource(string $file): bool
    {
        return $this->isUnder($file, $this->phelInternalSrcDir)
            || $this->isInternalArtifact($file);
    }

    /**
     * Whether the compiled file is a regenerable artifact with no meaning to a
     * user: the eval temp file (`__phel_<hash>.php`) or the compiled cache,
     * whose filename embeds a content hash and changes between runs. A
     * persistent build artifact under `out/` is not one: naming it is what the
     * stale-output hint builds on.
     */
    public function isInternalArtifact(string $file): bool
    {
        return str_starts_with(basename($file), '__phel')
            || $this->isUnder($file, $this->cacheDir);
    }

    private function isUnder(string $file, string $directory): bool
    {
        if ($directory === '') {
            return false;
        }

        // A PHAR stream path always uses `/`, whatever the host separator is.
        $normalizedDirectory = rtrim(str_replace('\\', '/', $directory), '/') . '/';

        return str_starts_with(str_replace('\\', '/', $file), $normalizedDirectory);
    }
}
