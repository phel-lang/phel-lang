<?php

declare(strict_types=1);

namespace Phel\Run\Application\Bench;

use function bin2hex;
use function file_exists;
use function is_dir;
use function is_link;
use function mkdir;
use function random_bytes;
use function realpath;
use function rmdir;
use function scandir;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;
use function sys_get_temp_dir;
use function trim;
use function unlink;

/**
 * A detached `git worktree` of the ref side A runs from, under the system
 * temp dir. The user's own working tree is never checked out or stashed.
 * {@see remove()} is idempotent, so the `finally` of a run and a signal
 * handler can both call it.
 *
 * @internal
 */
final class TemporaryWorktree
{
    private bool $removed = false;

    private function __construct(
        private readonly ChildProcess $process,
        private readonly string $repositoryRoot,
        private readonly string $root,
        public readonly string $commit,
    ) {}

    /**
     * Resolves the ref and picks the temp path without creating anything, so
     * a caller holding the result can clean up whatever {@see add()} got to
     * before a signal arrived.
     *
     * @throws AbBenchException when `$projectDir` is not a git repository root or `$ref` names no commit
     */
    public static function forRef(ChildProcess $process, string $projectDir, string $ref): self
    {
        [$status, $toplevel] = $process->capture(['git', 'rev-parse', '--show-toplevel'], $projectDir);
        if ($status !== 0 || trim($toplevel) === '') {
            throw new AbBenchException(sprintf('--ab needs a git repository around %s and `git` on the PATH.', $projectDir));
        }

        $repositoryRoot = realpath(trim($toplevel)) ?: trim($toplevel);
        if ($repositoryRoot !== $projectDir) {
            throw new AbBenchException(sprintf('Run phel bench --ab from the repository root, %s.', $repositoryRoot));
        }

        // A leading dash would reach git as an option rather than a ref.
        [$status, $commit] = str_starts_with($ref, '-')
            ? [1, '']
            : $process->capture(['git', 'rev-parse', '--verify', '--quiet', $ref . '^{commit}'], $repositoryRoot);
        if ($status !== 0 || trim($commit) === '') {
            throw new AbBenchException(sprintf('git does not know the ref "%s".', $ref));
        }

        $tempDir = realpath(sys_get_temp_dir()) ?: sys_get_temp_dir();

        return new self($process, $repositoryRoot, $tempDir . '/phel-bench-ab-' . bin2hex(random_bytes(6)), trim($commit));
    }

    /**
     * @throws AbBenchException
     */
    public function add(string $ref): void
    {
        if (!@mkdir($this->root, 0o755, true) && !is_dir($this->root)) {
            throw new AbBenchException('Cannot create the temporary directory ' . $this->root);
        }

        [$status, , $stderr] = $this->process->capture(
            ['git', 'worktree', 'add', '--detach', '--quiet', $this->treePath(), $this->commit],
            $this->repositoryRoot,
        );
        if ($status !== 0) {
            throw new AbBenchException(sprintf('Cannot create a worktree at "%s": %s', $ref, trim($stderr)));
        }
    }

    /**
     * Scratch space for results and logs, removed with the worktree.
     */
    public function scratchPath(string $name): string
    {
        return $this->root . '/' . $name;
    }

    public function treePath(): string
    {
        return $this->root . '/tree';
    }

    /**
     * Where `$path` of the working tree lives in the worktree, or null when
     * it lies outside the repository or does not exist at the ref.
     */
    public function counterpartOf(string $path): ?string
    {
        if ($path !== $this->repositoryRoot && !str_starts_with($path, $this->repositoryRoot . '/')) {
            return null;
        }

        $candidate = $this->treePath() . substr($path, strlen($this->repositoryRoot));

        return file_exists($candidate) ? $candidate : null;
    }

    /**
     * The file's content at the ref, or null when it does not exist there.
     */
    public function contentAtRef(string $path): ?string
    {
        [$status, $content] = $this->process->capture(['git', 'show', $this->commit . ':' . $path], $this->repositoryRoot);

        return $status === 0 ? $content : null;
    }

    public function remove(): void
    {
        if ($this->removed) {
            return;
        }

        if (file_exists($this->treePath())) {
            // Twice: once for local changes, once more for a locked worktree.
            $this->process->capture(
                ['git', 'worktree', 'remove', '--force', '--force', $this->treePath()],
                $this->repositoryRoot,
            );
        }

        if (file_exists($this->treePath())) {
            $this->removeRecursively($this->treePath());
            $this->process->capture(['git', 'worktree', 'prune'], $this->repositoryRoot);
        }

        $this->removeRecursively($this->root);
        // Set last: a signal arriving mid-removal runs the whole removal again.
        $this->removed = true;
    }

    /**
     * Unlinks symlinks instead of following them: side A's vendor entries
     * point into the working tree's vendor.
     */
    private function removeRecursively(string $path): void
    {
        if (is_link($path) || !is_dir($path)) {
            if (file_exists($path) || is_link($path)) {
                @unlink($path);
            }

            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->removeRecursively($path . '/' . $entry);
            }
        }

        @rmdir($path);
    }
}
