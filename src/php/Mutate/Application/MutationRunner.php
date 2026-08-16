<?php

declare(strict_types=1);

namespace Phel\Mutate\Application;

use Closure;
use Phel\Mutate\Domain\Exception\BaselineFailedException;
use Phel\Mutate\Domain\Exception\WorkerFailedException;
use Phel\Mutate\Domain\Mutant;
use Phel\Mutate\Domain\MutantResult;
use Phel\Mutate\Domain\MutantVerdict;
use Phel\Mutate\Domain\MutationReport;
use Phel\Shared\ScalarCoercion;

use function is_array;
use function is_string;
use function max;
use function microtime;
use function sprintf;

/**
 * Drives one worker through the whole run: load the project, take the
 * baseline, then feed every mutant and collect its verdict. A worker that
 * dies (a mutant that recursed into a stack overflow, say) or stays silent
 * past the deadline is replaced by a fresh one, and the mutant is scored
 * as detected: killed when the worker died, timeout when it did not answer.
 *
 * @phpstan-type Frame array<string, mixed>
 *
 * @internal
 */
final class MutationRunner
{
    public const string TYPE_LOAD = 'load';

    public const string TYPE_BASELINE = 'baseline';

    public const string TYPE_MUTANT = 'mutant';

    /** Never wait less than this per mutant, whatever the baseline took. */
    private const float MIN_TIMEOUT_SECONDS = 1.0;

    /** Loading a cold project may compile everything; give it room. */
    private const float LOAD_TIMEOUT_SECONDS = 600.0;

    private ?MutantWorker $worker = null;

    private float $baselineSeconds = 0.0;

    /**
     * @param Closure(): MutantWorker $spawnWorker
     * @param list<string>            $loadOrder      absolute `.phel` files, dependencies first
     * @param list<string>            $testNamespaces
     */
    public function __construct(
        private readonly Closure $spawnWorker,
        private readonly array $loadOrder,
        private readonly array $testNamespaces,
        private readonly float $timeoutFactor,
    ) {}

    /**
     * @param list<Mutant>                     $mutants
     * @param Closure(MutantResult): void|null $onResult called after every mutant, for progress
     */
    public function run(array $mutants, ?Closure $onResult = null): MutationReport
    {
        try {
            $this->startWorker();
            $this->baselineSeconds = $this->takeBaseline();
            $timeout = max(self::MIN_TIMEOUT_SECONDS, $this->baselineSeconds * $this->timeoutFactor);

            $results = [];
            foreach ($mutants as $mutant) {
                $result = $this->runMutant($mutant, $timeout);
                $results[] = $result;
                if ($onResult instanceof Closure) {
                    $onResult($result);
                }
            }

            return new MutationReport($results, $this->baselineSeconds);
        } finally {
            $this->worker?->terminate();
        }
    }

    private function runMutant(Mutant $mutant, float $timeout): MutantResult
    {
        $startedAt = microtime(true);
        $answer = $this->worker()->request([
            'type' => self::TYPE_MUTANT,
            'ns' => $mutant->namespace,
            'code' => $mutant->mutatedForm,
            'restore' => $mutant->originalForm,
        ], $timeout);
        $seconds = microtime(true) - $startedAt;

        if ($answer === null) {
            $died = !$this->worker()->isAlive();
            $stderr = $this->worker()->readStderr();
            $this->restartWorker();

            return $died
                ? new MutantResult($mutant, MutantVerdict::Killed, $seconds, 'the worker died running the tests' . ($stderr === '' ? '' : ': ' . $stderr))
                : new MutantResult($mutant, MutantVerdict::Timeout, $seconds, sprintf('no answer within %.1fs', $timeout));
        }

        $verdict = MutantVerdict::tryFrom(ScalarCoercion::toString($answer['verdict'] ?? null)) ?? MutantVerdict::Error;
        $detail = $answer['detail'] ?? null;

        return new MutantResult($mutant, $verdict, $seconds, is_string($detail) ? $detail : null);
    }

    private function takeBaseline(): float
    {
        $answer = $this->worker()->request(['type' => self::TYPE_BASELINE], self::LOAD_TIMEOUT_SECONDS);
        if (!is_array($answer)) {
            throw new WorkerFailedException('The mutation worker did not answer the baseline run: ' . $this->worker()->readStderr());
        }

        if (!(bool) ($answer['passed'] ?? false)) {
            throw new BaselineFailedException(sprintf(
                'The unmutated test suite is not green (%d assertion(s) ran); fix it before mutating.',
                ScalarCoercion::toInt($answer['total'] ?? null),
            ));
        }

        return ScalarCoercion::toFloat($answer['seconds'] ?? null);
    }

    private function startWorker(): MutantWorker
    {
        $worker = ($this->spawnWorker)();
        $this->worker = $worker;

        $answer = $worker->request([
            'type' => self::TYPE_LOAD,
            'files' => $this->loadOrder,
            'tests' => $this->testNamespaces,
        ], self::LOAD_TIMEOUT_SECONDS);

        if (!is_array($answer) || !(bool) ($answer['ok'] ?? false)) {
            $error = is_array($answer) ? ScalarCoercion::toString($answer['error'] ?? null) : $worker->readStderr();
            throw new WorkerFailedException('The mutation worker could not load the project: ' . $error);
        }

        return $worker;
    }

    private function restartWorker(): void
    {
        $this->worker?->terminate();
        $this->startWorker();
    }

    private function worker(): MutantWorker
    {
        return $this->worker ?? $this->startWorker();
    }
}
