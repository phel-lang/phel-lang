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

use function implode;
use function is_array;
use function is_string;
use function json_decode;
use function microtime;
use function ob_start;
use function sprintf;

/**
 * What the `_mutate-worker` subprocess does between frames: load the
 * project once, run the unmutated suite as the baseline, and then, per
 * mutant, redefine one `defn` in its namespace, run the tests, and put the
 * original definition back. Runs the tests in-process, so a mutant that
 * loops forever or blows the stack takes the worker with it; the parent
 * treats a dead or silent worker as a detected mutant and starts a fresh
 * one (see {@see MutationRunner}).
 *
 * @phpstan-type TestOutcome array{passed: bool, total: int, seconds: float}
 * @phpstan-type MutantOutcome array{verdict: string, seconds: float, detail: ?string}
 *
 * @internal
 */
final class MutantWorkerSession
{
    /** @var list<string> namespaces the tests live in, from the load frame */
    private array $testNamespaces = [];

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
     * @return TestOutcome
     */
    public function baseline(): array
    {
        return $this->runTests();
    }

    /**
     * Redefine, test, restore. A mutant that does not compile is an
     * `error`, one the tests catch is `killed`, one they miss `survived`.
     * The original definition is restored even when the tests throw.
     *
     * @return MutantOutcome
     */
    public function mutant(string $namespace, string $mutatedForm, string $originalForm): array
    {
        $startedAt = microtime(true);
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
            $outcome = $this->runTests();
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
     * @return TestOutcome
     */
    private function runTests(): array
    {
        $namespaces = [];
        foreach ($this->testNamespaces as $namespace) {
            $namespaces[] = "'" . $namespace;
        }

        $code = sprintf(
            '(do (phel.test/run-tests {:reporters []} %s) '
            . '(phel.json/encode {"passed" (phel.test/successful?) '
            . '"total" (get (get (phel.test/get-stats) :counts) :total)}))',
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
