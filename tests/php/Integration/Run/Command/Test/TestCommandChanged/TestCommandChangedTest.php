<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Test\TestCommandChanged;

use Override;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function escapeshellarg;
use function exec;
use function file_put_contents;
use function implode;
use function mkdir;
use function random_bytes;
use function sprintf;
use function sys_get_temp_dir;

/**
 * `phel test --changed` on a throwaway project inside a throwaway git
 * repository: a change to a source namespace selects the tests that
 * transitively require it, a change to a test file selects only that
 * namespace, a clean tree with nothing to compare against selects nothing
 * and says so, and a directory without git is an error, not a silent full run.
 */
final class TestCommandChangedTest extends TestCase
{
    private string $projectDir;

    private string $repoRoot;

    #[Override]
    protected function setUp(): void
    {
        $this->repoRoot = dirname(__DIR__, 7);
        $this->projectDir = sys_get_temp_dir() . '/phel-changed-' . bin2hex(random_bytes(8));
        mkdir($this->projectDir . '/src/app', 0o755, true);
        mkdir($this->projectDir . '/tests/app', 0o755, true);
        mkdir($this->projectDir . '/vendor', 0o755, true);
        file_put_contents(
            $this->projectDir . '/vendor/autoload.php',
            sprintf("<?php return require '%s/vendor/autoload.php';\n", $this->repoRoot),
        );
        file_put_contents(
            $this->projectDir . '/phel-config.php',
            "<?php\nreturn new \\Phel\\Config\\PhelConfig()\n"
            . "    ->withSrcDirs(['src'])->withTestDirs(['tests'])->withVendorDir('');\n",
        );
        file_put_contents($this->projectDir . '/.gitignore', "vendor/\n.phel/\n");
        file_put_contents($this->projectDir . '/src/app/util.phel', "(ns app.util)\n\n(defn twice [x] (* 2 x))\n");
        file_put_contents(
            $this->projectDir . '/src/app/calc.phel',
            "(ns app.calc\n  (:require app.util :as u))\n\n(defn add [a b] (+ a b))\n\n(defn double-add [a b] (u/twice (add a b)))\n",
        );
        file_put_contents($this->projectDir . '/src/app/lonely.phel', "(ns app.lonely)\n\n(defn nobody-calls-me [] :alone)\n");
        file_put_contents(
            $this->projectDir . '/tests/app/util_test.phel',
            "(ns app.util-test\n  (:require phel.test :refer [deftest is])\n  (:require app.util :as u))\n\n(deftest twice-works\n  (is (= 4 (u/twice 2))))\n",
        );
        file_put_contents(
            $this->projectDir . '/tests/app/calc_test.phel',
            "(ns app.calc-test\n  (:require phel.test :refer [deftest is])\n  (:require app.calc :as c))\n\n(deftest add-works\n  (is (= 3 (c/add 1 2))))\n",
        );
        file_put_contents(
            $this->projectDir . '/tests/app/other_test.phel',
            "(ns app.other-test\n  (:require phel.test :refer [deftest is]))\n\n(deftest other-works\n  (is (= 1 1)))\n",
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->projectDir));
    }

    public function test_a_changed_source_selects_the_tests_that_transitively_require_it(): void
    {
        $this->gitInitAndCommit();
        file_put_contents($this->projectDir . '/src/app/util.phel', "(ns app.util)\n\n(defn twice [x] (+ x x))\n");

        [$exitCode, $output] = $this->runPhelTest(['--changed', '--list']);

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('Changed: 1 file(s), affected: 2 test namespace(s)', $output);
        self::assertStringContainsString('app.util-test/twice-works', $output);
        self::assertStringContainsString('app.calc-test/add-works', $output);
        self::assertStringNotContainsString('app.other-test', $output);
    }

    public function test_a_changed_test_file_selects_only_its_own_namespace_and_runs_it(): void
    {
        $this->gitInitAndCommit();
        file_put_contents(
            $this->projectDir . '/tests/app/other_test.phel',
            "(ns app.other-test\n  (:require phel.test :refer [deftest is]))\n\n(deftest other-works\n  (is (= 1 1))\n  (is (= 2 2)))\n",
        );

        [$exitCode, $output] = $this->runPhelTest(['--changed']);

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('Changed: 1 file(s), affected: 1 test namespace(s)', $output);
        self::assertMatchesRegularExpression('/Passed:\s*2/', $output);
    }

    public function test_an_untracked_new_test_file_counts_as_changed(): void
    {
        $this->gitInitAndCommit();
        file_put_contents(
            $this->projectDir . '/tests/app/new_test.phel',
            "(ns app.new-test\n  (:require phel.test :refer [deftest is]))\n\n(deftest brand-new\n  (is true))\n",
        );

        [$exitCode, $output] = $this->runPhelTest(['--changed', '--list']);

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('affected: 1 test namespace(s)', $output);
        self::assertStringContainsString('app.new-test/brand-new', $output);
    }

    public function test_a_change_nobody_requires_selects_nothing_and_says_so(): void
    {
        $this->gitInitAndCommit();
        file_put_contents($this->projectDir . '/src/app/lonely.phel', "(ns app.lonely)\n\n(defn nobody-calls-me [] :still-alone)\n");

        [$exitCode, $output] = $this->runPhelTest(['--changed']);

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('Changed: 1 file(s), affected: 0 test namespace(s)', $output);
        self::assertStringContainsString('nothing to run', $output);
        self::assertStringNotContainsString('Passed:', $output);
    }

    public function test_an_explicit_ref_compares_against_it(): void
    {
        $this->gitInitAndCommit();
        file_put_contents($this->projectDir . '/src/app/util.phel', "(ns app.util)\n\n(defn twice [x] (+ x x))\n");
        $this->git('add -A');
        $this->git('commit -q -m "change util"');

        [$exitCode, $output] = $this->runPhelTest(['--changed=HEAD~1', '--list']);

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('affected: 2 test namespace(s)', $output);
    }

    public function test_outside_a_git_repository_it_is_an_error(): void
    {
        [$exitCode, $output] = $this->runPhelTest(['--changed']);

        self::assertSame(1, $exitCode, $output);
        self::assertStringContainsString('needs a git repository', $output);
    }

    private function gitInitAndCommit(): void
    {
        $this->git('init -q');
        $this->git('add -A');
        $this->git('commit -q -m init');
    }

    /**
     * Every git call carries its own identity and ignores the developer's
     * global config, so the test behaves the same on a CI runner with no
     * `user.email` and on a machine with commit signing turned on.
     */
    private function git(string $arguments): void
    {
        exec(sprintf(
            'cd %s && GIT_CONFIG_GLOBAL=/dev/null GIT_CONFIG_SYSTEM=/dev/null git -c user.email=t@t -c user.name=t -c commit.gpgsign=false %s 2>&1',
            escapeshellarg($this->projectDir),
            $arguments,
        ), $output, $status);
        self::assertSame(0, $status, 'git ' . $arguments . ': ' . implode("\n", $output));
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{0: int, 1: string}
     */
    private function runPhelTest(array $arguments): array
    {
        $args = '';
        foreach ($arguments as $argument) {
            $args .= ' ' . escapeshellarg($argument);
        }

        // Never let the developer's own git config (signing, hooks) leak in.
        $cmd = 'cd ' . escapeshellarg($this->projectDir)
            . ' && GIT_CONFIG_GLOBAL=/dev/null GIT_CONFIG_SYSTEM=/dev/null php ' . escapeshellarg($this->repoRoot . '/bin/phel')
            . ' test' . $args . ' 2>&1';

        exec($cmd, $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }
}
