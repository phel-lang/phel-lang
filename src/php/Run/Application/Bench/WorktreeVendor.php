<?php

declare(strict_types=1);

namespace Phel\Run\Application\Bench;

use Symfony\Component\Console\Output\OutputInterface;

use function copy;
use function file_exists;
use function file_get_contents;
use function in_array;
use function is_dir;
use function is_file;
use function is_link;
use function mkdir;
use function realpath;
use function scandir;
use function sprintf;
use function symlink;
use function trim;

/**
 * Gives side A a `vendor/` of its own.
 *
 * With the same `composer.lock` at both refs the installed packages are
 * reused, but not by linking `vendor/` itself: Composer's autoloader finds
 * the project through `__DIR__`, which resolves a symlink, so a linked
 * `vendor/` maps the project's own namespaces (`Phel\` in this repository)
 * to the working tree and side A silently runs side B's code. Instead every
 * package is linked, and `vendor/composer/` plus `vendor/autoload.php`
 * are copied, so the autoloader resolves the project to the worktree.
 *
 * @internal
 */
final readonly class WorktreeVendor
{
    private const array COPIED_ENTRIES = ['autoload.php', 'composer'];

    public function __construct(
        private ChildProcess $process,
    ) {}

    public function prepare(string $projectDir, TemporaryWorktree $worktree, string $ref, OutputInterface $output): void
    {
        $vendor = realpath($projectDir . '/vendor');
        $target = $worktree->treePath() . '/vendor';
        if ($vendor === false || !is_dir($vendor) || file_exists($target)) {
            return;
        }

        $lockFile = $projectDir . '/composer.lock';
        $lockHere = is_file($lockFile) ? file_get_contents($lockFile) : null;
        if ($lockHere === $worktree->contentAtRef('composer.lock')) {
            $this->mirror($vendor, $target);

            return;
        }

        $output->writeln(sprintf('composer.lock differs at %s: running composer install --no-dev in the worktree.', $ref));
        [$status, , $stderr] = $this->process->capture(
            ['composer', 'install', '--no-dev', '--quiet', '--no-interaction'],
            $worktree->treePath(),
        );
        if ($status !== 0) {
            throw new AbBenchException(sprintf('composer install failed in the worktree of %s: %s', $ref, trim($stderr)));
        }
    }

    private function mirror(string $vendor, string $target): void
    {
        mkdir($target, 0o755, true);

        foreach (scandir($vendor) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $source = $vendor . '/' . $entry;
            if (in_array($entry, self::COPIED_ENTRIES, true)) {
                $this->copyRecursively($source, $target . '/' . $entry);
            } elseif (!@symlink($source, $target . '/' . $entry)) {
                throw new AbBenchException(sprintf('Cannot link %s into the worktree.', $source));
            }
        }
    }

    private function copyRecursively(string $source, string $target): void
    {
        if (is_link($source) || !is_dir($source)) {
            copy($source, $target);

            return;
        }

        mkdir($target, 0o755, true);
        foreach (scandir($source) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->copyRecursively($source . '/' . $entry, $target . '/' . $entry);
            }
        }
    }
}
