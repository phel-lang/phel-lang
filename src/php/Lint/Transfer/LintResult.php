<?php

declare(strict_types=1);

namespace Phel\Lint\Transfer;

use Phel\Api\Transfer\Diagnostic;

use function array_map;
use function count;

/**
 * Aggregate outcome of a lint run: the flat list of diagnostics plus
 * per-severity counters used to drive the process exit code.
 */
final readonly class LintResult
{
    /**
     * @param list<Diagnostic> $diagnostics
     * @param list<string>     $scannedFiles
     */
    public function __construct(
        public array $diagnostics,
        public array $scannedFiles = [],
    ) {}

    public function errorCount(): int
    {
        return $this->countBySeverity(Diagnostic::SEVERITY_ERROR);
    }

    public function warningCount(): int
    {
        return $this->countBySeverity(Diagnostic::SEVERITY_WARNING);
    }

    public function infoCount(): int
    {
        return $this->countBySeverity(Diagnostic::SEVERITY_INFO);
    }

    public function hintCount(): int
    {
        return $this->countBySeverity(Diagnostic::SEVERITY_HINT);
    }

    public function hasErrors(): bool
    {
        return $this->errorCount() > 0;
    }

    public function totalCount(): int
    {
        return count($this->diagnostics);
    }

    /**
     * @return list<array{
     *     code: string,
     *     severity: string,
     *     message: string,
     *     uri: string,
     *     startLine: int,
     *     startCol: int,
     *     endLine: int,
     *     endCol: int,
     * }>
     */
    public function toArray(): array
    {
        return array_map(static fn(Diagnostic $d): array => $d->toArray(), $this->diagnostics);
    }

    private function countBySeverity(string $severity): int
    {
        $count = 0;
        foreach ($this->diagnostics as $diagnostic) {
            if ($diagnostic->severity === $severity) {
                ++$count;
            }
        }

        return $count;
    }
}
