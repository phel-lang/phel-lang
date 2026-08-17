<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function implode;
use function json_encode;
use function round;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * The outcome of one `phel mutate` run: every mutant with its verdict, the
 * totals, and the mutation score indicator (killed over killed plus
 * survived; errors and timeouts do not count against the suite, timeouts
 * count as killed the way Infection counts them).
 *
 * @internal
 */
final readonly class MutationReport
{
    /**
     * @param list<MutantResult> $results
     * @param string             $coverageDriver the driver that attributed tests to lines, '' when every mutant ran the whole suite
     */
    public function __construct(
        public array $results,
        public float $baselineSeconds,
        public string $coverageDriver = '',
    ) {}

    public function total(): int
    {
        return count($this->results);
    }

    public function count(MutantVerdict $verdict): int
    {
        return count($this->of($verdict));
    }

    /**
     * @return list<MutantResult>
     */
    public function of(MutantVerdict $verdict): array
    {
        return array_values(array_filter(
            $this->results,
            static fn(MutantResult $result): bool => $result->verdict === $verdict,
        ));
    }

    /**
     * Mutation score indicator in percent: detected mutants (killed or
     * timed out) over detected, survived and not covered. 100 when nothing
     * could be scored. Errors (mutants that do not compile) never count.
     */
    public function msi(): float
    {
        return $this->percentage($this->detected(), $this->detected() + $this->count(MutantVerdict::Survived) + $this->count(MutantVerdict::NotCovered));
    }

    /**
     * The same score over the mutants some test actually reaches: detected
     * over detected plus survived. Equal to {@see msi()} when no coverage
     * attribution ran.
     */
    public function coveredMsi(): float
    {
        return $this->percentage($this->detected(), $this->detected() + $this->count(MutantVerdict::Survived));
    }

    public function meetsMinimum(?float $minMsi, ?float $minCoveredMsi = null): bool
    {
        return ($minMsi === null || $this->msi() >= $minMsi)
            && ($minCoveredMsi === null || $this->coveredMsi() >= $minCoveredMsi);
    }

    public function toText(): string
    {
        $lines = [];
        $lines[] = sprintf(
            'Mutants: %d  Killed: %d  Survived: %d  Not covered: %d  Errors: %d  Timeouts: %d',
            $this->total(),
            $this->count(MutantVerdict::Killed),
            $this->count(MutantVerdict::Survived),
            $this->count(MutantVerdict::NotCovered),
            $this->count(MutantVerdict::Error),
            $this->count(MutantVerdict::Timeout),
        );
        $lines[] = sprintf('MSI: %.1f%%  Covered MSI: %.1f%%  Baseline: %.1fs', $this->msi(), $this->coveredMsi(), $this->baselineSeconds);
        $lines[] = $this->coverageDriver === ''
            ? 'Coverage: none (every mutant ran the whole suite)'
            : sprintf('Coverage: %s (each mutant ran only the tests that reach its definition)', $this->coverageDriver);

        $survived = $this->of(MutantVerdict::Survived);
        if ($survived !== []) {
            $lines[] = '';
            $lines[] = 'Survived:';
            foreach ($survived as $result) {
                $lines[] = $this->describe($result->mutant);
            }
        }

        $notCovered = $this->of(MutantVerdict::NotCovered);
        if ($notCovered !== []) {
            $lines[] = '';
            $lines[] = 'Not covered by any test:';
            foreach ($notCovered as $result) {
                $lines[] = self::describe($result->mutant);
            }
        }

        $errors = $this->of(MutantVerdict::Error);
        if ($errors !== []) {
            $lines[] = '';
            $lines[] = 'Errors (did not compile):';
            foreach ($errors as $result) {
                $lines[] = $this->describe($result->mutant) . ($result->detail === null ? '' : ': ' . $result->detail);
            }
        }

        return implode("\n", $lines) . "\n";
    }

    public function toJson(): string
    {
        return json_encode(
            [
                'baselineSeconds' => round($this->baselineSeconds, 3),
                'coverage' => $this->coverageDriver,
                'totals' => [
                    'mutants' => $this->total(),
                    'killed' => $this->count(MutantVerdict::Killed),
                    'survived' => $this->count(MutantVerdict::Survived),
                    'notCovered' => $this->count(MutantVerdict::NotCovered),
                    'errors' => $this->count(MutantVerdict::Error),
                    'timeouts' => $this->count(MutantVerdict::Timeout),
                    'msi' => round($this->msi(), 2),
                    'coveredMsi' => round($this->coveredMsi(), 2),
                ],
                'mutants' => array_map(
                    static fn(MutantResult $result): array => [
                        'file' => $result->mutant->file,
                        'line' => $result->mutant->line,
                        'column' => $result->mutant->column,
                        'namespace' => $result->mutant->namespace,
                        'definition' => $result->mutant->definition,
                        'mutator' => $result->mutant->mutator,
                        'description' => $result->mutant->description,
                        'verdict' => $result->verdict->value,
                        'seconds' => round($result->seconds, 3),
                        'detail' => $result->detail,
                        'diff' => $result->mutant->diff(),
                    ],
                    $this->results,
                ),
            ],
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        ) . "\n";
    }

    private function detected(): int
    {
        return $this->count(MutantVerdict::Killed) + $this->count(MutantVerdict::Timeout);
    }

    private function percentage(int $part, int $whole): float
    {
        if ($whole === 0) {
            return 100.0;
        }

        return (float) $part / (float) $whole * 100.0;
    }

    private function describe(Mutant $mutant): string
    {
        return sprintf('  %s:%d [%s] %s', $mutant->file, $mutant->line, $mutant->mutator, $mutant->description);
    }
}
