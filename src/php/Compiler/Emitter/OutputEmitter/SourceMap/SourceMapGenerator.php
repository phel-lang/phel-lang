<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\SourceMap;

final class SourceMapGenerator
{
    private VLQ $vlq;

    public function __construct()
    {
        $this->vlq = new VLQ();
    }

    public function encode(array $mappings): string
    {
        $previousGeneratedLine = 0;
        $previousGeneratedColumn = 0;
        $previousOriginalLine = 0;
        $previousOriginalColumn = 0;
        $result = '';

        $totalMappings = count($mappings);
        for ($i = 0; $i < $totalMappings; $i++) {
            $mapping = $mappings[$i];

            if ($mapping['generated']['line'] !== $previousGeneratedLine) {
                $previousGeneratedColumn = 0;

                $result .= str_repeat(';', $mapping['generated']['line'] - $previousGeneratedLine);
                $previousGeneratedLine = $mapping['generated']['line'];
            } elseif ($i > 0) {
                if (!$this->compareByGeneratedPositionsInflated($mapping, $mappings[$i - 1])) {
                    continue;
                }
                $result .= ',';
            }

            $result .= $this->vlq->encodeIntegers([
                $mapping['generated']['column'] - $previousGeneratedColumn,
                0,
                $mapping['original']['line'] - $previousOriginalLine,
                $mapping['original']['column'] - $previousOriginalColumn,
            ]);

            $previousGeneratedColumn = $mapping['generated']['column'];
            $previousOriginalLine = $mapping['original']['line'];
            $previousOriginalColumn = $mapping['original']['column'];
        }

        return $result;
    }

    private function compareByGeneratedPositionsInflated(array $mappingA, array $mappingB): int
    {
        /** @var int $cmp */
        $cmp = $mappingA['generated']['line'] - $mappingB['generated']['line'];
        if ($cmp !== 0) {
            return $cmp;
        }

        $cmp = $mappingA['generated']['column'] - $mappingB['generated']['column'];
        if ($cmp !== 0) {
            return $cmp;
        }

        $cmp = $mappingA['original']['line'] - $mappingB['original']['line'];
        if ($cmp !== 0) {
            return $cmp;
        }

        return $mappingA['original']['column'] - $mappingB['original']['column'];
    }
}
