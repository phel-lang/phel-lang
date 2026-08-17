<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Git;

use Phel\Run\Domain\Test\ChangedFilesFinderInterface;
use Phel\Run\Domain\Test\ChangedFilesUnavailableException;

use function array_merge;
use function array_unique;
use function escapeshellarg;
use function exec;
use function realpath;
use function sprintf;
use function trim;

/**
 * Asks git which files changed. Every call shells out to the `git` on
 * `PATH` inside the project directory; nothing is cached, a `--changed`
 * run asks once.
 *
 * @internal
 */
final class GitChangedFiles implements ChangedFilesFinderInterface
{
    private const array DEFAULT_BRANCH_CANDIDATES = ['origin/HEAD', 'origin/main', 'main', 'origin/master', 'master'];

    public function changedFiles(?string $ref, string $projectDir): array
    {
        $root = $this->repositoryRoot($projectDir);

        if ($ref !== null) {
            return $this->diffAgainst($root, $ref);
        }

        $uncommitted = $this->diffAgainst($root, 'HEAD');
        if ($uncommitted !== []) {
            return $uncommitted;
        }

        $base = $this->mergeBaseWithDefaultBranch($root);

        return $base === null ? [] : $this->diffAgainst($root, $base);
    }

    private function repositoryRoot(string $projectDir): string
    {
        [$status, $lines] = $this->git($projectDir, 'rev-parse --show-toplevel');
        if ($status !== 0 || $lines === []) {
            throw new ChangedFilesUnavailableException(sprintf(
                '--changed needs a git repository around %s and `git` on the PATH.',
                $projectDir,
            ));
        }

        $root = realpath($lines[0]);

        return $root === false ? $lines[0] : $root;
    }

    /**
     * Tracked changes against `$ref` plus untracked files, as absolute paths.
     *
     * @return list<string>
     */
    private function diffAgainst(string $root, string $ref): array
    {
        [$status, $changed] = $this->git($root, 'diff --name-only ' . escapeshellarg($ref) . ' --');
        if ($status !== 0) {
            throw new ChangedFilesUnavailableException(sprintf('git does not know the ref "%s".', $ref));
        }

        [, $untracked] = $this->git($root, 'ls-files --others --exclude-standard');

        $files = [];
        foreach (array_unique(array_merge($changed, $untracked)) as $relative) {
            if ($relative !== '') {
                $files[] = $root . '/' . $relative;
            }
        }

        return $files;
    }

    private function mergeBaseWithDefaultBranch(string $root): ?string
    {
        foreach (self::DEFAULT_BRANCH_CANDIDATES as $candidate) {
            [$status, $lines] = $this->git($root, 'merge-base HEAD ' . escapeshellarg($candidate));
            if ($status === 0 && isset($lines[0]) && trim($lines[0]) !== '') {
                return trim($lines[0]);
            }
        }

        return null;
    }

    /**
     * @return array{0: int, 1: list<string>} exit status and stdout lines
     */
    private function git(string $cwd, string $arguments): array
    {
        $output = [];
        $status = 1;
        exec(sprintf('cd %s && git %s 2>/dev/null', escapeshellarg($cwd), $arguments), $output, $status);

        return [$status, $output];
    }
}
