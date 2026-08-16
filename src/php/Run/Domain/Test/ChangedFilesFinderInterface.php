<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Test;

/**
 * The files a `--changed` run considers modified. The only implementation
 * asks git; the interface exists so the selection logic is testable without
 * a repository.
 *
 * @internal
 */
interface ChangedFilesFinderInterface
{
    /**
     * Absolute paths of the files changed since `$ref` (a commit, branch or
     * tag), or, with a null `$ref`, the uncommitted changes against `HEAD`,
     * falling back to the changes since the merge base with the default
     * branch when the working tree is clean. Untracked files count.
     *
     * @throws ChangedFilesUnavailableException when the directory is not in a git repository or git cannot be run
     *
     * @return list<string>
     */
    public function changedFiles(?string $ref, string $projectDir): array;
}
