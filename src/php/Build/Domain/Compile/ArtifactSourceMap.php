<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

use Phel\Shared\SourceMap\BuiltFilePreamble;
use Phel\Shared\SourceMap\VLQ;

/**
 * @phpstan-type ColumnEdit array{column: int, length: int, replacementLength: int}
 *
 * @internal
 */
final class ArtifactSourceMap
{
    private function __construct() {}

    /**
     * @param array<int, list<ColumnEdit>> $edits
     */
    public static function rewrite(string $mappings, array $edits): string
    {
        if ($mappings === '' || $edits === []) {
            return $mappings;
        }

        $vlq = new VLQ();
        $lines = explode(';', $mappings);
        foreach ($lines as $index => $line) {
            $lineEdits = $edits[$index + BuiltFilePreamble::codeStartLine()] ?? [];
            if ($lineEdits === []) {
                continue;
            }

            $originalColumn = 0;
            $previousColumn = 0;
            $segments = explode(',', $line);
            foreach ($segments as $segmentIndex => $segment) {
                if ($segment === '') {
                    continue;
                }

                $values = $vlq->decode($segment);
                $originalColumn += $values[0];
                $column = self::adjustColumn($originalColumn, $lineEdits);
                $values[0] = $column - $previousColumn;
                $previousColumn = $column;
                $segments[$segmentIndex] = $vlq->encodeIntegers($values);
            }

            $lines[$index] = implode(',', $segments);
        }

        return implode(';', $lines);
    }

    /**
     * @param list<ColumnEdit> $edits
     */
    private static function adjustColumn(int $column, array $edits): int
    {
        $offset = 0;
        foreach ($edits as $edit) {
            if ($column < $edit['column']) {
                break;
            }

            if ($column < $edit['column'] + $edit['length']) {
                return $edit['column'] + $offset;
            }

            $offset += $edit['replacementLength'] - $edit['length'];
        }

        return $column + $offset;
    }
}
