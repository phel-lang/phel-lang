<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\SourceMap;

use Phel\Shared\SourceMap\VLQ;

final class SourceMapConsumer
{
    /** @var array<int, list<int>> */
    private array $lineMapping;

    private readonly VLQ $vlq;

    public function __construct(string $mapping)
    {
        $this->vlq = new VLQ();
        $this->lineMapping = $this->decodeMapping($mapping);
    }

    public function getOriginalLine(int $generatedLine): ?int
    {
        $mappings = $this->lineMapping[$generatedLine] ?? [];

        return $mappings === [] ? null : min($mappings);
    }

    /**
     * Every mapped generated line and the original Phel line it points at
     * (the earliest, matching {@see getOriginalLine}). Used to enumerate the
     * coverable Phel lines of a compiled file for coverage reporting.
     *
     * @return array<int, int> generated line (1-based) => original Phel line
     */
    public function getMappedLines(): array
    {
        $result = [];
        foreach ($this->lineMapping as $generatedLine => $originalLines) {
            if ($originalLines !== []) {
                $result[$generatedLine] = min($originalLines);
            }
        }

        return $result;
    }

    /**
     * @return array<int, list<int>>
     */
    private function decodeMapping(string $mapping): array
    {
        $lines = explode(';', $mapping);

        $lineMapping = [];
        $lastMapping = [0, 0, 0, 0];
        foreach ($lines as $i => $line) {
            $segments = explode(',', $line);

            foreach ($segments as $segment) {
                if ($segment !== '') {
                    $relMapping = $this->vlq->decode($segment);

                    $absMapping = [
                        $lastMapping[0] + $relMapping[0], // generated column
                        $lastMapping[1] + $relMapping[1], // source
                        $lastMapping[2] + $relMapping[2], // original line
                        $lastMapping[3] + $relMapping[3],  // original column
                    ];

                    $lastMapping = $absMapping;

                    $lineMapping[$i + 1][] = $absMapping[2] + 1;
                }
            }
        }

        return $lineMapping;
    }
}
