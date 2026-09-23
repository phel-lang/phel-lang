<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Bench;

use Override;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function bin2hex;
use function count;
use function dirname;
use function escapeshellarg;
use function exec;
use function file_put_contents;
use function implode;
use function mkdir;
use function preg_match;
use function random_bytes;
use function sprintf;
use function sys_get_temp_dir;

/**
 * `phel bench --ab` against a throwaway git repository whose committed code
 * differs from its working tree, so a run that measured the same side twice
 * cannot pass.
 */
final class BenchAbCommandTest extends TestCase
{
    private const string SLOW_SPEED = <<<'PHP'
        <?php

        namespace Fixture;

        final class Speed
        {
            public static function run(): void
            {
                usleep(3000);
            }
        }
        PHP;

    private const string FAST_SPEED = <<<'PHP'
        <?php

        namespace Fixture;

        final class Speed
        {
            public static function run(): void
            {
            }
        }
        PHP;

    private const string BENCH = <<<'PHEL'
        (ns fixture.speed
          (:use Fixture.Speed)
          (:require phel.bench :refer [defbench]))

        (defbench bench-speed
          {:revs 1 :iterations 1 :warmup 0}
          (Speed/run))
        PHEL;

    private const string BROKEN_BENCH = <<<'PHEL'
        (ns fixture.speed
          (:require phel.bench :refer [defbench]))

        (defbench bench-speed
          {:revs 1 :iterations 1 :warmup 0}
          (throw (new RuntimeException "side a is broken")))
        PHEL;

    private string $repoRoot;

    private string $projectDir;

    #[Override]
    protected function setUp(): void
    {
        $this->repoRoot = dirname(__DIR__, 6);
        $this->projectDir = sys_get_temp_dir() . '/phel-bench-ab-fixture-' . bin2hex(random_bytes(8));
        mkdir($this->projectDir . '/src', 0o755, true);
        mkdir($this->projectDir . '/bench', 0o755, true);
        mkdir($this->projectDir . '/vendor', 0o755, true);

        // An untracked, ignored vendor/ that maps `Fixture\` through its own
        // `__DIR__`: side A resolves it to the worktree only when its
        // autoloader lives there rather than behind a symlink.
        file_put_contents($this->projectDir . '/vendor/autoload.php', sprintf(
            <<<'PHP'
                <?php
                spl_autoload_register(static function (string $class): void {
                    if (str_starts_with($class, 'Fixture\\')) {
                        require __DIR__ . '/../src/' . substr($class, 8) . '.php';
                    }
                });
                return require '%s/vendor/autoload.php';
                PHP,
            $this->repoRoot,
        ));

        file_put_contents($this->projectDir . '/phel-config.php', sprintf(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use Phel\Config\PhelConfig;

                return new PhelConfig()
                    ->withSrcDirs(['%s/src/phel'])
                    ->withTestDirs(['bench'])
                    ->withVendorDir('');
                PHP,
            $this->repoRoot,
        ));

        file_put_contents($this->projectDir . '/.gitignore', "vendor/\n.phel/\n");
        file_put_contents($this->projectDir . '/composer.lock', "{}\n");
    }

    #[Override]
    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->projectDir));
    }

    public function test_it_measures_the_ref_and_the_working_tree_apart(): void
    {
        $this->commit(self::SLOW_SPEED, self::BENCH);
        file_put_contents($this->projectDir . '/src/Speed.php', self::FAST_SPEED);

        [$exitCode, $output] = $this->runPhel(['bench', '--ab=HEAD', '--pairs=2']);

        self::assertSame(0, $exitCode, $output);
        // The committed class sleeps and the working tree's does not: every
        // pair must be faster. The same side measured twice would read as noise.
        self::assertMatchesRegularExpression('#fixture\.speed/bench-speed\s+\S+\s+\S+\s+-9\d\.\d\d%\s+2/2$#m', $output);
        $this->assertWorktreeIsGone($output);
    }

    public function test_it_removes_the_worktree_after_a_failing_run(): void
    {
        $this->commit(self::FAST_SPEED, self::BROKEN_BENCH);
        file_put_contents($this->projectDir . '/bench/speed.phel', self::BENCH);

        [$exitCode, $output] = $this->runPhel(['bench', '--ab=HEAD', '--pairs=2']);

        self::assertSame(1, $exitCode, $output);
        self::assertStringContainsString('Side A failed in pair 1', $output);
        self::assertStringContainsString('side a is broken', $output);
        $this->assertWorktreeIsGone($output);
    }

    public function test_it_rejects_an_unknown_ref(): void
    {
        $this->commit(self::FAST_SPEED, self::BENCH);

        [$exitCode, $output] = $this->runPhel(['bench', '--ab=no-such-ref']);

        self::assertSame(1, $exitCode, $output);
        self::assertStringContainsString('git does not know the ref "no-such-ref"', $output);
        self::assertSame(1, $this->worktreeCount());
    }

    /**
     * @param list<string> $arguments
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidOptions')]
    public function test_it_rejects_invalid_options(array $arguments, string $message): void
    {
        [$exitCode, $output] = $this->runPhel(['bench', ...$arguments]);

        self::assertSame(2, $exitCode, $output);
        self::assertStringContainsString($message, $output);
    }

    /**
     * @return iterable<string, array{0: list<string>, 1: string}>
     */
    public static function invalidOptions(): iterable
    {
        yield 'with --store' => [['--ab=HEAD', '--store=baseline.json'], '--ab cannot be combined with --store'];
        yield 'with --ref' => [['--ab=HEAD', '--ref=baseline.json'], '--ab cannot be combined with --ref'];
        yield 'zero pairs' => [['--ab=HEAD', '--pairs=0'], '--pairs must be a whole number of at least 1'];
        yield 'fractional pairs' => [['--ab=HEAD', '--pairs=1.5'], '--pairs must be a whole number of at least 1'];
        yield 'pairs without --ab' => [['--pairs=3'], '--pairs only applies together with --ab'];
    }

    private function commit(string $speed, string $bench): void
    {
        file_put_contents($this->projectDir . '/src/Speed.php', $speed);
        file_put_contents($this->projectDir . '/bench/speed.phel', $bench);

        $this->git('init --quiet');
        $this->git('add --all');
        $this->git('-c user.name=phel -c user.email=phel@example.com -c commit.gpgsign=false commit --quiet -m fixture');
    }

    private function assertWorktreeIsGone(string $output): void
    {
        self::assertSame(1, preg_match('/^A: HEAD \(\w+\), in (\S+)$/m', $output, $matches), $output);
        self::assertDirectoryDoesNotExist(dirname($matches[1]));
        self::assertSame(1, $this->worktreeCount());
    }

    private function worktreeCount(): int
    {
        return count($this->git('worktree list --porcelain | grep "^worktree "'));
    }

    /**
     * @return list<string>
     */
    private function git(string $arguments): array
    {
        exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($this->projectDir), $arguments), $output, $status);
        if ($status !== 0) {
            throw new RuntimeException('git ' . $arguments . ' failed: ' . implode("\n", $output));
        }

        return $output;
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{0: int, 1: string}
     */
    private function runPhel(array $arguments): array
    {
        $command = sprintf(
            'cd %s && php %s %s 2>&1',
            escapeshellarg($this->projectDir),
            escapeshellarg($this->repoRoot . '/bin/phel'),
            implode(' ', array_map(escapeshellarg(...), $arguments)),
        );
        exec($command, $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }
}
