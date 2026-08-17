<?php

declare(strict_types=1);

namespace Phel\Mutate\Application;

use Phel\Lang\Registry;
use Phel\Mutate\Domain\MutantVerdict;
use Phel\Shared\CompileOptions;
use Phel\Shared\CompilerConstants;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Facade\RunFacadeInterface;
use Phel\Shared\ReplConstants;
use Phel\Shared\ScalarCoercion;
use Throwable;

use function array_keys;
use function implode;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function microtime;
use function ob_start;
use function realpath;
use function sprintf;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * What the `_mutate-worker` subprocess does between frames: load the
 * project once, run the unmutated suite as the baseline, and then, per
 * mutant, redefine one `defn` in its namespace, run the tests, and put the
 * original definition back. Runs the tests in-process, so a mutant that
 * loops forever or blows the stack takes the worker with it; the parent
 * treats a dead or silent worker as a detected mutant and starts a fresh
 * one (see {@see MutationRunner}).
 *
 * @phpstan-type TestOutcome array{passed: bool, total: int, seconds: float, coverage?: string, testsByLine?: array<string, array<int, list<string>>>}
 * @phpstan-type MutantOutcome array{verdict: string, seconds: float, detail: ?string}
 *
 * @internal
 */
final class MutantWorkerSession
{
    /** @var list<string> namespaces the tests live in, from the load frame */
    private array $testNamespaces = [];

    /** @var array<string, array<int, list<string>>> phelFile => line => "ns/test-name", from the baseline; empty without a coverage driver */
    private array $testsByLine = [];

    private bool $attributed = false;

    public function __construct(
        private readonly RunFacadeInterface $runFacade,
        private readonly CompilerFacadeInterface $compilerFacade,
    ) {}

    /**
     * Evaluate the project files in dependency order and switch the
     * analyzer to interactive mode, so a later re-definition of a `defn`
     * is accepted instead of raising a duplicate-definition error.
     *
     * @param list<string> $files          absolute `.phel` paths, dependencies first
     * @param list<string> $testNamespaces
     */
    public function load(array $files, array $testNamespaces): void
    {
        foreach ($files as $file) {
            $this->runFacade->evalFile($this->runFacade->getNamespaceFromFile($file));
        }

        $this->testNamespaces = $testNamespaces;
        Registry::getInstance()->addDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, ReplConstants::INTERACTIVE_MODE, true);
    }

    /**
     * The unmutated run. When a coverage driver is available it also
     * records which tests reach which project lines, so every mutant after
     * it runs only the tests that can catch it, and a definition no test
     * reaches is reported as such without running anything.
     *
     * @return TestOutcome
     */
    public function baseline(): array
    {
        $driver = $this->runFacade->beginPerTestCoverage();
        try {
            $outcome = $this->runTests();
        } finally {
            if ($driver !== null) {
                $this->testsByLine = $this->runFacade->perTestCoverageByLine();
                $this->attributed = true;
                $this->runFacade->endPerTestCoverage();
            }
        }

        return $outcome + ['coverage' => $driver ?? '', 'testsByLine' => $this->testsByLine];
    }

    /**
     * Adopt the attribution another worker's baseline produced, so a pool
     * takes one baseline instead of one per worker (and the suite is not
     * run N times at once, which tests sharing a fixed temp path do not
     * survive). An empty map still counts as attributed: it means no test
     * reaches any project line.
     *
     * @param array<string, array<int, list<string>>> $testsByLine
     */
    public function adoptCoverage(array $testsByLine): void
    {
        $this->testsByLine = $testsByLine;
        $this->attributed = true;
    }

    /**
     * Redefine, test, restore. A mutant that does not compile is an
     * `error`, one the tests catch is `killed`, one they miss `survived`,
     * and when the baseline attributed coverage, one no test reaches is
     * `not-covered` without running anything. The original definition is
     * restored even when the tests throw.
     *
     * @param array{0: int, 1: int}|null $lines first and last line of the definition in `$file`
     *
     * @return MutantOutcome
     */
    public function mutant(string $namespace, string $mutatedForm, string $originalForm, string $file = '', ?array $lines = null): array
    {
        $startedAt = microtime(true);
        $onlyTests = $this->attributed && $lines !== null ? $this->testsReaching($file, $lines) : null;
        if ($onlyTests === []) {
            return ['verdict' => MutantVerdict::NotCovered->value, 'seconds' => 0.0, 'detail' => null];
        }

        try {
            $this->evaluateIn($namespace, $mutatedForm);
        } catch (Throwable $throwable) {
            $this->restore($namespace, $originalForm);

            return [
                'verdict' => MutantVerdict::Error->value,
                'seconds' => microtime(true) - $startedAt,
                'detail' => $throwable->getMessage(),
            ];
        }

        try {
            $outcome = $this->runTests($onlyTests);
            $verdict = $outcome['passed'] ? MutantVerdict::Survived : MutantVerdict::Killed;
            $detail = null;
        } catch (Throwable $throwable) {
            $verdict = MutantVerdict::Killed;
            $detail = 'the test run threw: ' . $throwable->getMessage();
        } finally {
            $this->restore($namespace, $originalForm);
        }

        return ['verdict' => $verdict->value, 'seconds' => microtime(true) - $startedAt, 'detail' => $detail];
    }

    /**
     * The `ns/test-name`s whose coverage touches any line of `[$from, $to]`
     * in `$file`, or null when the file was never attributed (a file the
     * project dirs do not contain, say), meaning "run everything".
     *
     * @param array{0: int, 1: int} $lines
     *
     * @return list<string>
     */
    private function testsReaching(string $file, array $lines): array
    {
        $byLine = $this->testsByLine[$file] ?? null;
        if ($byLine === null) {
            $real = realpath($file);
            $byLine = $real === false ? null : ($this->testsByLine[$real] ?? null);
        }

        if ($byLine === null) {
            return [];
        }

        $tests = [];
        for ($line = $lines[0]; $line <= $lines[1]; ++$line) {
            foreach ($byLine[$line] ?? [] as $test) {
                $tests[$test] = true;
            }
        }

        return array_keys($tests);
    }

    /**
     * @param list<string>|null $onlyTests exact `ns/test-name`s to run; null runs every test
     *
     * @return TestOutcome
     */
    private function runTests(?array $onlyTests = null): array
    {
        $namespaces = [];
        foreach ($this->testNamespaces as $namespace) {
            $namespaces[] = "'" . $namespace;
        }

        $options = '{:reporters []}';
        if ($onlyTests !== null) {
            $quoted = [];
            foreach ($onlyTests as $test) {
                $quoted[] = json_encode($test, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            }

            $options = sprintf('{:reporters [] :only-tests [%s]}', implode(' ', $quoted));
        }

        $code = sprintf(
            '(do (phel.test/run-tests %s %s) '
            . '(phel.json/encode {"passed" (phel.test/successful?) '
            . '"total" (get (get (phel.test/get-stats) :counts) :total)}))',
            $options,
            implode(' ', $namespaces),
        );

        $startedAt = microtime(true);
        ob_start();
        try {
            $result = $this->runFacade->eval($code, new CompileOptions()->setIsEnabledSourceMaps(false));
        } finally {
            ob_end_clean();
        }

        $decoded = is_string($result) ? json_decode($result, true) : null;
        $passed = is_array($decoded) && (bool) ($decoded['passed'] ?? false);
        $total = is_array($decoded) ? ScalarCoercion::toInt($decoded['total'] ?? null) : 0;

        return ['passed' => $passed, 'total' => $total, 'seconds' => microtime(true) - $startedAt];
    }

    private function evaluateIn(string $namespace, string $code): void
    {
        $this->compilerFacade->getGlobalEnvironment()->setNs($namespace);
        $this->runFacade->eval($code, new CompileOptions()->setIsEnabledSourceMaps(false));
    }

    private function restore(string $namespace, string $originalForm): void
    {
        try {
            $this->evaluateIn($namespace, $originalForm);
        } catch (Throwable) {
            // The original compiled once, when the project loaded; if it does
            // not now, the process state is beyond repair and the parent will
            // notice on the next baseline-shaped failure.
        }
    }
}
