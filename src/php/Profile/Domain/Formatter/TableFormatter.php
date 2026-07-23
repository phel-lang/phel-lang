<?php

declare(strict_types=1);

namespace Phel\Profile\Domain\Formatter;

use Phel\Profile\Domain\ProfileReport;
use Phel\Profile\Domain\SortOrder;

use function array_keys;
use function array_map;
use function array_slice;
use function array_sum;
use function count;
use function max;
use function number_format;
use function rtrim;
use function sprintf;
use function str_pad;
use function str_replace;
use function strlen;
use function strrpos;
use function substr;
use function uasort;

/**
 * @phpstan-import-type FnStat from ProfileReport
 */
final class TableFormatter
{
    /**
     * Build the multi-section report: an optional compile-phase table, the
     * runtime fn table (top `$top` rows ordered by `$sort`), and a wall-clock
     * footer. Empty sections are omitted.
     *
     * @param int  $top                  Maximum fn rows to show; the rest are truncated
     * @param bool $includeCompilePhases Prepend the compile-phase table when phase data exists
     */
    public function render(ProfileReport $report, int $top, SortOrder $sort, bool $includeCompilePhases): string
    {
        $out = '';

        if ($includeCompilePhases && $report->phaseMs() !== []) {
            $out .= $this->renderCompilePhases($report) . "\n";
        }

        $out .= $this->renderFnStats($report, $top, $sort);

        return $out . sprintf("\nWall-clock total: %s ms\n", $this->fmt($report->wallClockMs()));
    }

    private function renderCompilePhases(ProfileReport $report): string
    {
        $phases = ['lex', 'parse', 'read', 'analyze', 'emit'];
        $rows = $this->buildPhaseRows($report, $phases);

        $sourceWidth = $this->calculateColumnWidth(array_map(static fn(array $r): string => (string) $r['source'], $rows));
        $header = $this->phaseHeader($sourceWidth, $phases);
        $body = '';
        foreach ($rows as $row) {
            $body .= '  ' . str_pad((string) $row['source'], $sourceWidth);
            foreach ($phases as $p) {
                $body .= '  ' . str_pad($this->fmt((float) $row[$p]), 8, ' ', STR_PAD_LEFT);
            }

            $body .= '  ' . str_pad($this->fmt((float) $row['total']), 8, ' ', STR_PAD_LEFT) . "\n";
        }

        return "Compile-time phases (ms)\n" . $header . $body;
    }

    /**
     * @param list<string> $phases
     *
     * @return list<array<string, float|string>>
     */
    private function buildPhaseRows(ProfileReport $report, array $phases): array
    {
        $rows = [];
        foreach ($report->phaseMs() as $source => $byPhase) {
            $row = ['source' => $this->shortenSource($source)];
            $total = 0.0;
            foreach ($phases as $p) {
                $val = $byPhase[$p] ?? 0.0;
                $row[$p] = $val;
                $total += $val;
            }

            $row['total'] = $total;
            $rows[] = $row;
        }

        uasort($rows, static fn(array $a, array $b): int => $b['total'] <=> $a['total']);

        return array_values($rows);
    }

    /**
     * @param list<string> $phases
     */
    private function phaseHeader(int $sourceWidth, array $phases): string
    {
        $line = '  ' . str_pad('source', $sourceWidth);
        foreach ($phases as $p) {
            $line .= '  ' . str_pad($p, 8, ' ', STR_PAD_LEFT);
        }

        return $line . '  ' . str_pad('total', 8, ' ', STR_PAD_LEFT) . "\n";
    }

    private function renderFnStats(ProfileReport $report, int $top, SortOrder $sort): string
    {
        $stats = $report->fnStats();
        if ($stats === []) {
            return "Runtime fn profile: no profiled calls recorded.\n";
        }

        uasort($stats, $this->sortComparator($sort));
        $rows = array_slice($stats, 0, $top, true);

        $names = array_map($this->displayName(...), array_keys($rows));
        $nameWidth = $this->calculateColumnWidth($names);

        $out = sprintf("Runtime fn profile (top %d, sort by %s)\n", count($rows), $sort->value);
        $out .= $this->renderFnHeader($nameWidth);

        foreach ($rows as $boundTo => $r) {
            $out .= $this->renderFnRow($this->displayName($boundTo), $r, $nameWidth);
        }

        $totalSelfMs = array_sum(array_map(static fn(array $r): float => (float) $r['selfNs'] / 1_000_000.0, $stats));
        $totalCalls = array_sum(array_map(static fn(array $r): int => $r['calls'], $stats));

        return $out . sprintf("\nTotals: %d fns, %d calls, %s ms self.\n", count($stats), $totalCalls, $this->fmt($totalSelfMs));
    }

    private function renderFnHeader(int $nameWidth): string
    {
        return '  ' . str_pad('fn', $nameWidth) . '  '
            . str_pad('calls', 9, ' ', STR_PAD_LEFT) . '  '
            . str_pad('self ms', 10, ' ', STR_PAD_LEFT) . '  '
            . str_pad('total ms', 10, ' ', STR_PAD_LEFT) . '  '
            . str_pad('avg us', 10, ' ', STR_PAD_LEFT) . '  '
            . str_pad('max us', 10, ' ', STR_PAD_LEFT) . "\n";
    }

    /**
     * @param FnStat $r
     */
    private function renderFnRow(string $name, array $r, int $nameWidth): string
    {
        $avgUs = $r['calls'] > 0 ? ((float) $r['totalNs'] / (float) $r['calls']) / 1_000.0 : 0.0;
        $maxUs = (float) $r['maxNs'] / 1_000.0;
        $selfMs = (float) $r['selfNs'] / 1_000_000.0;
        $totalMs = (float) $r['totalNs'] / 1_000_000.0;

        return '  ' . str_pad($name, $nameWidth) . '  '
            . str_pad((string) $r['calls'], 9, ' ', STR_PAD_LEFT) . '  '
            . str_pad($this->fmt($selfMs), 10, ' ', STR_PAD_LEFT) . '  '
            . str_pad($this->fmt($totalMs), 10, ' ', STR_PAD_LEFT) . '  '
            . str_pad($this->fmt($avgUs), 10, ' ', STR_PAD_LEFT) . '  '
            . str_pad($this->fmt($maxUs), 10, ' ', STR_PAD_LEFT) . "\n";
    }

    /**
     * @return callable(FnStat, FnStat): int
     */
    private function sortComparator(SortOrder $sort): callable
    {
        return match ($sort) {
            SortOrder::TotalTime => static fn(array $a, array $b): int => $b['totalNs'] <=> $a['totalNs'],
            SortOrder::Calls => static fn(array $a, array $b): int => $b['calls'] <=> $a['calls'],
            SortOrder::Avg => static fn(array $a, array $b): int => ((float) $b['totalNs'] / (float) max($b['calls'], 1)) <=> ((float) $a['totalNs'] / (float) max($a['calls'], 1)),
            SortOrder::SelfTime => static fn(array $a, array $b): int => $b['selfNs'] <=> $a['selfNs'],
        };
    }

    /**
     * @param list<string> $values
     */
    private function calculateColumnWidth(array $values, int $minWidth = 20): int
    {
        return max($minWidth, ...array_map(strlen(...), $values));
    }

    private function displayName(string $boundTo): string
    {
        $lastSep = strrpos($boundTo, '\\');
        if ($lastSep === false) {
            return str_replace('_', '-', $boundTo);
        }

        $ns = substr($boundTo, 0, $lastSep);
        $name = substr($boundTo, $lastSep + 1);

        return rtrim(str_replace('\\', '.', $ns), '.') . '/' . str_replace('_', '-', $name);
    }

    private function shortenSource(string $source): string
    {
        if (strlen($source) <= 40) {
            return $source;
        }

        return '…' . substr($source, -39);
    }

    private function fmt(float $val): string
    {
        return number_format($val, 2, '.', '');
    }
}
