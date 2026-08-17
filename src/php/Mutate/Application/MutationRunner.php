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
use Phel\Mutate\Domain\TestsByLine;
use Phel\Shared\ScalarCoercion;

use function array_key_exists;
use function array_key_first;
use function array_shift;
use function count;
use function is_array;
use function is_string;
use function max;
use function microtime;
use function min;
use function sprintf;
use function usleep;

/**
 * Drives a pool of workers through the whole run: every worker loads the
 * project and takes the baseline (the first one decides whether the suite
 * is green and how long it takes), then the mutants are handed out as
 * workers come free. A worker that dies (a mutant that recursed into a
 * stack overflow, say) or stays silent past the deadline is replaced by a
 * fresh one, and the mutant is scored as detected: killed when the worker
 * died, timeout when it did not answer.
 *
 * @internal
 */
final class MutationRunner
{
    public const string TYPE_LOAD = 'load';

    public const string TYPE_BASELINE = 'baseline';

    public const string TYPE_MUTANT = 'mutant';

    public const string TYPE_COVERAGE = 'coverage';

    /** Never wait less than this per mutant, whatever the baseline took. */
    private const float MIN_TIMEOUT_SECONDS = 1.0;

    /** Loading a cold project may compile everything; give it room. */
    private const float LOAD_TIMEOUT_SECONDS = 600.0;

    private const int IDLE_SLEEP_MICROS = 20_000;

    /** @var array<int, MutantWorker> pool slot => worker */
    private array $workers = [];

    /** @var array<int, Mutant> worker index => the mutant it is running */
    private array $assigned = [];

    /** @var array<int, float> worker index => when its mutant was sent */
    private array $sentAt = [];

    private float $baselineSeconds = 0.0;

    private string $coverageDriver = '';

    /** @var array<string, array<int, list<string>>>|null what the baseline attributed, null without a driver */
    private ?array $testsByLine = null;

    /**
     * @param Closure(): MutantWorker $spawnWorker
     * @param list<string>            $loadOrder      absolute `.phel` files, dependencies first
     * @param list<string>            $testNamespaces
     * @param int                     $workerCount    subprocesses to keep busy, at least 1
     */
    public function __construct(
        private readonly Closure $spawnWorker,
        private readonly array $loadOrder,
        private readonly array $testNamespaces,
        private readonly float $timeoutFactor,
        private readonly int $workerCount = 1,
    ) {}

    /**
     * @param list<Mutant>                     $mutants
     * @param Closure(MutantResult): void|null $onResult called after every mutant, for progress
     */
    public function run(array $mutants, ?Closure $onResult = null): MutationReport
    {
        try {
            $count = max(1, min($this->workerCount, max(1, count($mutants))));
            $this->startPool($count);

            $timeout = max(self::MIN_TIMEOUT_SECONDS, $this->baselineSeconds * $this->timeoutFactor);
            $results = $this->dispatchAll($mutants, $timeout, $onResult);

            return new MutationReport($results, $this->baselineSeconds, $this->coverageDriver);
        } finally {
            foreach ($this->workers as $worker) {
                $worker->terminate();
            }
        }
    }

    /**
     * @param list<Mutant>                     $mutants
     * @param Closure(MutantResult): void|null $onResult
     *
     * @return list<MutantResult>
     */
    private function dispatchAll(array $mutants, float $timeout, ?Closure $onResult): array
    {
        $queue = $mutants;
        $results = [];

        while ($queue !== [] || $this->assigned !== []) {
            foreach ($this->workers as $index => $worker) {
                if (!$worker->isBusy() && $queue !== []) {
                    $mutant = array_shift($queue);
                    $this->assign($index, $mutant, $timeout);
                }
            }

            $progressed = false;
            foreach ($this->workers as $index => $worker) {
                if (!isset($this->assigned[$index])) {
                    continue;
                }

                $result = $this->collect($index, $worker, $timeout);
                if (!$result instanceof MutantResult) {
                    continue;
                }

                $progressed = true;
                $results[] = $result;
                if ($onResult instanceof Closure) {
                    $onResult($result);
                }
            }

            if (!$progressed) {
                $this->waitForAnyWorker();
            }
        }

        return $results;
    }

    private function assign(int $index, Mutant $mutant, float $timeout): void
    {
        $this->assigned[$index] = $mutant;
        $this->sentAt[$index] = microtime(true);
        $this->workers[$index]->send([
            'type' => self::TYPE_MUTANT,
            'ns' => $mutant->namespace,
            'code' => $mutant->mutatedForm,
            'restore' => $mutant->originalForm,
            'file' => $mutant->file,
            'lines' => $mutant->lineRange(),
        ], $timeout);
    }

    /**
     * The result of the mutant on `$worker`, or null while it is still running.
     */
    private function collect(int $index, MutantWorker $worker, float $timeout): ?MutantResult
    {
        $mutant = $this->assigned[$index];
        $seconds = microtime(true) - $this->sentAt[$index];

        $answer = $worker->tryReceive();
        if ($answer !== null) {
            unset($this->assigned[$index], $this->sentAt[$index]);

            $verdict = MutantVerdict::tryFrom(ScalarCoercion::toString($answer['verdict'] ?? null)) ?? MutantVerdict::Error;
            $detail = $answer['detail'] ?? null;

            return new MutantResult($mutant, $verdict, $seconds, is_string($detail) ? $detail : null);
        }

        if ($worker->isDead()) {
            $stderr = $worker->readStderr();
            $this->replaceWorker($index);

            return new MutantResult(
                $mutant,
                MutantVerdict::Killed,
                $seconds,
                'the worker died running the tests' . ($stderr === '' ? '' : ': ' . $stderr),
            );
        }

        if ($worker->isOverdue()) {
            $this->replaceWorker($index);

            return new MutantResult($mutant, MutantVerdict::Timeout, $seconds, sprintf('no answer within %.1fs', $timeout));
        }

        return null;
    }

    private function replaceWorker(int $index): void
    {
        unset($this->assigned[$index], $this->sentAt[$index]);
        $this->workers[$index]->terminate();
        $this->workers[$index] = $this->startWorker();
    }

    private function waitForAnyWorker(): void
    {
        $first = array_key_first($this->assigned);
        if ($first === null) {
            usleep(self::IDLE_SLEEP_MICROS);

            return;
        }

        $this->workers[$first]->waitForOutput();
    }

    /**
     * Spawn every worker and load the project on all of them at once, then
     * take the baseline on the first: a red suite ends the run, its duration
     * sets the per-mutant timeout, and the coverage it attributed is handed
     * to the other workers, so the unmutated suite runs once, not once per
     * worker (nor N times at the same moment, which tests that share a fixed
     * temp path do not survive).
     */
    private function startPool(int $count): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $this->workers[$i] = ($this->spawnWorker)();
        }

        foreach ($this->awaitAll($this->workers, $this->loadFrame()) as $index => $answer) {
            $this->checkLoaded($this->workers[$index], $answer);
        }

        $this->checkBaseline(0, $this->workers[0]->request(['type' => self::TYPE_BASELINE], self::LOAD_TIMEOUT_SECONDS));

        $others = $this->workers;
        unset($others[0]);
        if ($this->testsByLine !== null && $others !== []) {
            foreach ($this->awaitAll($others, $this->coverageFrame()) as $index => $answer) {
                $this->checkLoaded($this->workers[$index], $answer);
            }
        }
    }

    /**
     * A replacement for a worker that died or hung: loaded on its own and
     * handed the baseline's attribution, since the pool is already running.
     */
    private function startWorker(): MutantWorker
    {
        $worker = ($this->spawnWorker)();
        try {
            $this->checkLoaded($worker, $worker->request($this->loadFrame(), self::LOAD_TIMEOUT_SECONDS));
            if ($this->testsByLine !== null) {
                $this->checkLoaded($worker, $worker->request($this->coverageFrame(), self::LOAD_TIMEOUT_SECONDS));
            }
        } catch (WorkerFailedException $workerFailedException) {
            $worker->terminate();
            throw $workerFailedException;
        }

        return $worker;
    }

    /**
     * @param array<string, mixed>|null $answer
     */
    private function checkLoaded(MutantWorker $worker, ?array $answer): void
    {
        if (!is_array($answer) || !(bool) ($answer['ok'] ?? false)) {
            $error = is_array($answer) ? ScalarCoercion::toString($answer['error'] ?? null) : $worker->readStderr();
            throw new WorkerFailedException('The mutation worker could not load the project: ' . $error);
        }
    }

    /**
     * Send `$frame` to each of `$workers` and wait for all answers.
     *
     * @param array<int, MutantWorker> $workers
     * @param array<string, mixed>     $frame
     *
     * @return array<int, array<string, mixed>|null> worker index => answer, null when it died or hung
     */
    private function awaitAll(array $workers, array $frame): array
    {
        foreach ($workers as $worker) {
            $worker->send($frame, self::LOAD_TIMEOUT_SECONDS);
        }

        $answers = [];
        while (count($answers) < count($workers)) {
            $progressed = false;
            foreach ($workers as $index => $worker) {
                if (isset($answers[$index])) {
                    continue;
                }

                if (array_key_exists($index, $answers)) {
                    continue;
                }

                $answer = $worker->tryReceive();
                if ($answer !== null) {
                    $answers[$index] = $answer;
                    $progressed = true;
                    continue;
                }

                if ($worker->isDead() || $worker->isOverdue()) {
                    $answers[$index] = null;
                    $progressed = true;
                }
            }

            if (!$progressed) {
                $workers[array_key_first($workers)]->waitForOutput();
            }
        }

        return $answers;
    }

    /**
     * @param array<string, mixed>|null $baseline
     */
    private function checkBaseline(int $index, ?array $baseline): void
    {
        if (!is_array($baseline)) {
            throw new WorkerFailedException('The mutation worker did not answer the baseline run: ' . $this->workers[$index]->readStderr());
        }

        if (!(bool) ($baseline['ok'] ?? false)) {
            throw new WorkerFailedException('The mutation worker failed the baseline run: ' . ScalarCoercion::toString($baseline['error'] ?? null));
        }

        if (!(bool) ($baseline['passed'] ?? false)) {
            throw new BaselineFailedException(sprintf(
                'The unmutated test suite is not green (%d assertion(s) ran); fix it before mutating.',
                ScalarCoercion::toInt($baseline['total'] ?? null),
            ));
        }

        if ($index === 0) {
            $this->baselineSeconds = ScalarCoercion::toFloat($baseline['seconds'] ?? null);
            $this->coverageDriver = ScalarCoercion::toString($baseline['coverage'] ?? null);
            $testsByLine = $baseline['testsByLine'] ?? null;
            $this->testsByLine = $this->coverageDriver !== '' && is_array($testsByLine) ? TestsByLine::fromWire($testsByLine) : null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function coverageFrame(): array
    {
        return ['type' => self::TYPE_COVERAGE, 'testsByLine' => $this->testsByLine ?? []];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFrame(): array
    {
        return [
            'type' => self::TYPE_LOAD,
            'files' => $this->loadOrder,
            'tests' => $this->testNamespaces,
        ];
    }
}
