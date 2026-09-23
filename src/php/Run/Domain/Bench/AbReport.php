<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Bench;

use function array_map;
use function implode;
use function max;
use function mb_str_pad;
use function mb_strlen;
use function number_format;
use function rtrim;
use function sprintf;

/**
 * Renders an {@see AbComparison} as the table `phel bench --ab` prints.
 * Durations use the units `phel.bench` prints, so the two tables read alike.
 *
 * @internal
 */
final class AbReport
{
    private const array HEADER = ['benchmark', 'a-mean', 'b-mean', 'delta', 'signs', ''];

    /**
     * @return list<string>
     */
    public function render(AbComparison $comparison): array
    {
        $rows = [self::HEADER];

        foreach ($comparison->rows() as $row) {
            $rows[] = [
                $row->name,
                $this->duration($row->meanA),
                $this->duration($row->meanB),
                $this->percent($row->meanDeltaPercent()),
                sprintf('%d/%d', $row->agreeingPairs(), $row->pairCount()),
                $row->isNoise() ? 'noise' : '',
            ];
        }

        foreach ($comparison->onlyInB() as $name) {
            $rows[] = [$name, '-', '', 'new', '', ''];
        }

        foreach ($comparison->onlyInA() as $name) {
            $rows[] = [$name, '', '-', 'gone', '', ''];
        }

        return $this->layout($rows);
    }

    /**
     * @param list<list<string>> $rows
     *
     * @return list<string>
     */
    private function layout(array $rows): array
    {
        $widths = [];
        foreach ($rows as $row) {
            foreach ($row as $column => $cell) {
                // `mb_*`: the `μs` unit is two bytes and one column.
                $widths[$column] = max($widths[$column] ?? 0, mb_strlen($cell));
            }
        }

        return array_map(
            static function (array $row) use ($widths): string {
                $cells = [];
                foreach ($row as $column => $cell) {
                    $cells[] = $column === 0
                        ? mb_str_pad($cell, $widths[$column])
                        : mb_str_pad($cell, $widths[$column], ' ', STR_PAD_LEFT);
                }

                return rtrim(implode(' ', $cells));
            },
            $rows,
        );
    }

    private function duration(float $nanoseconds): string
    {
        if ($nanoseconds < 1000) {
            return number_format($nanoseconds, 3) . 'ns';
        }

        if ($nanoseconds < 1_000_000) {
            return number_format($nanoseconds / 1000.0, 3) . 'μs';
        }

        return number_format($nanoseconds / 1_000_000.0, 3) . 'ms';
    }

    private function percent(float $value): string
    {
        return ($value >= 0 ? '+' : '') . number_format($value, 2) . '%';
    }
}
