<?php

declare(strict_types=1);

namespace PhelTest\Integration\Mutate;

use Override;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function bin2hex;
use function dirname;
use function escapeshellarg;
use function exec;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function json_decode;
use function ksort;
use function mkdir;
use function random_bytes;
use function sprintf;
use function sys_get_temp_dir;

use const JSON_THROW_ON_ERROR;

/**
 * End-to-end `phel mutate` on a throwaway project: a well-tested function
 * yields no survivors, an under-tested one names its escaped mutants, the
 * baseline gate refuses a red suite, and a mutant that loops forever is
 * scored as a timeout by the parent instead of hanging the run.
 */
final class MutateCommandTest extends TestCase
{
    private string $projectDir;

    private string $repoRoot;

    #[Override]
    protected function setUp(): void
    {
        $this->repoRoot = dirname(__DIR__, 4);
        $this->projectDir = sys_get_temp_dir() . '/phel-mutate-' . bin2hex(random_bytes(8));
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
    }

    #[Override]
    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->projectDir));
    }

    public function test_a_fully_tested_function_leaves_no_survivor_and_an_untested_branch_is_named(): void
    {
        $this->writeSource(<<<'PHEL'
        (ns app.calc)

        (defn add [a b]
          (+ a b))

        (defn clamp [x lo hi]
          (cond
            (< x lo) lo
            (> x hi) hi
            :else x))
        PHEL);
        $this->writeTests(<<<'PHEL'
        (ns app.calc-test
          (:require phel.test :refer [deftest is])
          (:require app.calc :as calc))

        (deftest add-works
          (is (= 3 (calc/add 1 2)))
          (is (= 0 (calc/add 0 0))))

        (deftest clamp-middle-only
          (is (= 5 (calc/clamp 5 0 10))))
        PHEL);

        [$exitCode, $output] = $this->runPhelMutate(['--reporter=json', '-o', $this->projectDir . '/mutation.json']);

        self::assertSame(0, $exitCode, $output);
        $report = json_decode((string) file_get_contents($this->projectDir . '/mutation.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($report);
        self::assertIsArray($report['mutants']);
        self::assertIsArray($report['totals']);
        self::assertSame(0, $report['totals']['errors'], $output);

        $survivors = [];
        foreach ($report['mutants'] as $mutant) {
            self::assertIsArray($mutant);
            if ($mutant['verdict'] === 'survived') {
                $survivors[] = $mutant['definition'] . ' ' . $mutant['mutator'] . ' ' . $mutant['description'];
            }
        }

        // `add` is pinned by two assertions: every mutant of it dies.
        foreach ($survivors as $survivor) {
            self::assertStringStartsNotWith('add ', $survivor, 'no mutant of add may survive: ' . $survivor);
        }

        // The boundaries of `clamp` are never exercised: both flips survive.
        self::assertContains('clamp compare (< x lo) -> (<= x lo)', $survivors, $output);
        self::assertContains('clamp compare (> x hi) -> (>= x hi)', $survivors, $output);
        self::assertStringContainsString('Survived:', $output);
        self::assertStringContainsString('src/app/calc.phel:8 [compare] (< x lo) -> (<= x lo)', $output);
    }

    public function test_the_min_msi_gate_fails_the_run(): void
    {
        $this->writeSource("(ns app.calc)\n\n(defn add [a b]\n  (+ a b))\n");
        $this->writeTests(
            "(ns app.calc-test\n  (:require phel.test :refer [deftest is])\n  (:require app.calc :as calc))\n\n"
            . "(deftest add-is-called\n  (calc/add 1 2)\n  (is true))\n",
        );

        [$exitCode, $output] = $this->runPhelMutate(['--min-msi=100']);

        self::assertSame(1, $exitCode, $output);
        self::assertStringContainsString('is below the required MSI 100.0%', $output);
    }

    public function test_a_red_baseline_refuses_to_mutate(): void
    {
        $this->writeSource("(ns app.calc)\n\n(defn add [a b]\n  (+ a b))\n");
        $this->writeTests(
            "(ns app.calc-test\n  (:require phel.test :refer [deftest is])\n  (:require app.calc :as calc))\n\n"
            . "(deftest add-is-wrong\n  (is (= 4 (calc/add 1 2))))\n",
        );

        [$exitCode, $output] = $this->runPhelMutate([]);

        self::assertSame(1, $exitCode, $output);
        self::assertStringContainsString('not green', $output);
    }

    public function test_a_mutant_that_never_terminates_is_a_timeout_not_a_hang(): void
    {
        // `(> n 0)` mutated to `(>= n 0)` still terminates; the arithmetic
        // mutant `(- n 1)` -> `(+ n 1)` never does. Only `arith` runs, so the
        // run has exactly one such mutant and finishes.
        $this->writeSource(
            "(ns app.loop)\n\n(defn countdown [n]\n  (if (> n 0)\n    (recur (- n 1))\n    :done))\n",
        );
        $this->writeTests(
            "(ns app.loop-test\n  (:require phel.test :refer [deftest is])\n  (:require app.loop :as l))\n\n"
            . "(deftest counts-down\n  (is (= :done (l/countdown 3))))\n",
        );

        [$exitCode, $output] = $this->runPhelMutate(['--only=arith', '--timeout-factor=1']);

        self::assertSame(0, $exitCode, $output);
        self::assertMatchesRegularExpression('/Timeouts: 1|Killed: 1/', $output);
        self::assertStringContainsString('Survived: 0', $output);
    }

    public function test_workers_share_the_mutants_and_the_verdicts_are_the_same_as_on_one(): void
    {
        $this->writeSource(<<<'PHEL'
        (ns app.calc)

        (defn add [a b]
          (+ a b))

        (defn clamp [x lo hi]
          (cond
            (< x lo) lo
            (> x hi) hi
            :else x))
        PHEL);
        $this->writeTests(<<<'PHEL'
        (ns app.calc-test
          (:require phel.test :refer [deftest is])
          (:require app.calc :as calc))

        (deftest add-works
          (is (= 3 (calc/add 1 2)))
          (is (= 0 (calc/add 0 0))))

        (deftest clamp-middle-only
          (is (= 5 (calc/clamp 5 0 10))))
        PHEL);

        [$serialExit, $serial] = $this->runPhelMutate(['--reporter=json', '-o', $this->projectDir . '/one.json']);
        [$parallelExit, $parallel] = $this->runPhelMutate(['--parallel=3', '--reporter=json', '-o', $this->projectDir . '/three.json']);

        self::assertSame(0, $serialExit, $serial);
        self::assertSame(0, $parallelExit, $parallel);
        self::assertStringContainsString('on 3 worker(s)', $parallel);
        self::assertSame(
            $this->verdictsOf($this->projectDir . '/one.json'),
            $this->verdictsOf($this->projectDir . '/three.json'),
            'the pool must not change a single verdict',
        );
    }

    public function test_with_a_coverage_driver_a_definition_no_test_reaches_is_not_covered_and_runs_nothing(): void
    {
        $this->writeSource("(ns app.calc)\n\n(defn add [a b]\n  (+ a b))\n\n(defn lonely [x]\n  (* x 2))\n");
        $this->writeTests(
            "(ns app.calc-test\n  (:require phel.test :refer [deftest is])\n  (:require app.calc :as calc))\n\n"
            . "(deftest add-works\n  (is (= 3 (calc/add 1 2)))\n  (is (= 0 (calc/add 0 0))))\n",
        );

        [$exitCode, $output] = $this->runPhelMutate(['--reporter=json', '-o', $this->projectDir . '/cov.json'], ['XDEBUG_MODE=coverage']);

        self::assertSame(0, $exitCode, $output);
        $report = json_decode((string) file_get_contents($this->projectDir . '/cov.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($report);
        if (($report['coverage'] ?? '') === '') {
            self::markTestSkipped('No coverage driver (pcov, or xdebug in coverage mode) in the worker; attribution is unproven here.');
        }

        self::assertIsArray($report['mutants']);
        $byDefinition = [];
        foreach ($report['mutants'] as $mutant) {
            self::assertIsArray($mutant);
            $byDefinition[$mutant['definition']][$mutant['verdict']] = ($byDefinition[$mutant['definition']][$mutant['verdict']] ?? 0) + 1;
        }

        self::assertArrayNotHasKey('survived', $byDefinition['add'] ?? [], 'add is fully tested: ' . $output);
        self::assertArrayNotHasKey('not-covered', $byDefinition['add'] ?? [], 'add is reached by a test: ' . $output);
        self::assertSame(['not-covered'], array_keys($byDefinition['lonely'] ?? []), 'no test reaches lonely: ' . $output);
        self::assertStringContainsString('Not covered by any test:', $output);
        self::assertStringContainsString('Coverage: ', $output);
    }

    public function test_changed_mutates_only_the_files_git_reports(): void
    {
        $this->writeSource("(ns app.calc)\n\n(defn add [a b]\n  (+ a b))\n");
        $this->writeSource("(ns app.other)\n\n(defn twice [x]\n  (* 2 x))\n");
        $this->writeTests(
            "(ns app.calc-test\n  (:require phel.test :refer [deftest is])\n  (:require app.calc :as calc)\n  (:require app.other :as o))\n\n"
            . "(deftest add-works\n  (is (= 3 (calc/add 1 2))))\n\n(deftest twice-works\n  (is (= 4 (o/twice 2))))\n",
        );
        $this->git('init -q');
        $this->git('add -A');
        $this->git('commit -q -m init');
        $this->writeSource("(ns app.other)\n\n(defn twice [x]\n  (+ x x))\n");

        [$exitCode, $output] = $this->runPhelMutate(['--changed', '--reporter=json', '-o', $this->projectDir . '/changed.json']);

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('Mutating 1 file(s)', $output);
        $report = json_decode((string) file_get_contents($this->projectDir . '/changed.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($report);
        self::assertIsArray($report['mutants']);
        foreach ($report['mutants'] as $mutant) {
            self::assertIsArray($mutant);
            self::assertSame('twice', $mutant['definition'], 'only the changed file is mutated');
        }
    }

    /**
     * @return array<string, string> mutant id => verdict
     */
    private function verdictsOf(string $jsonPath): array
    {
        $report = json_decode((string) file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($report);
        self::assertIsArray($report['mutants']);

        $verdicts = [];
        foreach ($report['mutants'] as $mutant) {
            self::assertIsArray($mutant);
            $verdicts[$mutant['file'] . ':' . $mutant['line'] . ' ' . $mutant['mutator'] . ' ' . $mutant['description']] = $mutant['verdict'];
        }

        ksort($verdicts);

        return $verdicts;
    }

    private function git(string $arguments): void
    {
        exec(sprintf(
            'cd %s && GIT_CONFIG_GLOBAL=/dev/null GIT_CONFIG_SYSTEM=/dev/null git -c user.email=t@t -c user.name=t -c commit.gpgsign=false %s 2>&1',
            escapeshellarg($this->projectDir),
            $arguments,
        ), $output, $status);
        self::assertSame(0, $status, 'git ' . $arguments . ': ' . implode("\n", $output));
    }

    private function writeSource(string $phel): void
    {
        file_put_contents($this->projectDir . '/src/app/' . $this->fileNameFor($phel), $phel . "\n");
    }

    private function writeTests(string $phel): void
    {
        file_put_contents($this->projectDir . '/tests/app/' . $this->fileNameFor($phel), $phel . "\n");
    }

    /**
     * `(ns app.calc-test ...)` lives in `calc_test.phel`, `(ns app.loop)` in `loop.phel`.
     */
    private function fileNameFor(string $phel): string
    {
        if (preg_match('/^\(ns app\.([a-z-]+)/', $phel, $m) !== 1) {
            self::fail('fixture source must start with (ns app.<name>');
        }

        return str_replace('-', '_', $m[1]) . '.phel';
    }

    /**
     * @param list<string> $arguments
     * @param list<string> $env       `NAME=value` pairs exported to the subprocess
     *
     * @return array{0: int, 1: string}
     */
    private function runPhelMutate(array $arguments, array $env = []): array
    {
        $args = '';
        foreach ($arguments as $argument) {
            $args .= ' ' . escapeshellarg($argument);
        }

        $cmd = 'cd ' . escapeshellarg($this->projectDir)
            . ' && ' . ($env === [] ? '' : implode(' ', $env) . ' ')
            . 'php ' . escapeshellarg($this->repoRoot . '/bin/phel')
            . ' mutate' . $args . ' 2>&1';

        exec($cmd, $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }
}
