<?php

namespace Phel\SourceMap;

class SourceMap {

    public function encode(array $mappings) {
        $previousGeneratedLine = 0;
        $previousGeneratedColumn = 0;
        $previousOriginalLine = 0;
        $previousOriginalColumn = 0;
        $vlq = new VLQ();
        $result = '';

        $totalMappings = count($mappings);
        for ($i = 0; $i < $totalMappings; $i++) {
            $mapping = $mappings[$i];
            $next = "";

            if ($mapping['generated']['line'] !== $previousGeneratedLine) {
                $previousGeneratedColumn = 0;
    
                while ($mapping['generated']['line'] !== $previousGeneratedLine) {
                    $next .= ';';
                    $previousGeneratedLine++;
                }
            } else if ($i > 0) {
                if (!$this->compareByGeneratedPositionsInflated($mapping, $mappings[$i - 1])) {
                    continue;
                }
                $next .= ",";
            }

            $next .= $vlq->encodeIntegers([
                $mapping['generated']['column'] - $previousGeneratedColumn,
                0,
                $mapping['original']['line'] - $previousOriginalLine,
                $mapping['original']['column'] - $previousOriginalColumn
            ]);

            $previousGeneratedColumn = $mapping['generated']['column'];
            $previousOriginalLine = $mapping['original']['line'];
            $previousOriginalColumn = $mapping['original']['column'];

            $result .= $next;
        }

        return $result;
    }

    private function compareByGeneratedPositionsInflated($mappingA, $mappingB) {
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

    /*public function encode(array $data) {
        $sourceIndex = 0;
        $lines = [];
        foreach ($data as $line => $columns) {
            foreach ($columns as $column => $infos) {
                foreach ($infos as $info) {
                    $phpLine = $info['phpLine'];
                    $phpCol = $info['phpCol'];

                    $segv = [$phpCol, $sourceIndex, $line, $column];
                    $lc = count($lines);

                    if ($phpLine > $lc - 1) {
                        for ($i = 0; $i < $phpLine - $lc; $i++) {
                            $lines[] = [];
                        }
                    }
                    $lines[$phpLine][] = $segv;
                }
            }
        }

        $vlq = new VLQ();

        $encodedLines = [];
        foreach ($lines as $line) {
            $encodedSegments = [];
            $lastSeg = null;
            foreach ($line as $i => $seg) {
                if ($lastSeg) {
                    $encodedSegments[] = $vlq->encodeIntegers([
                        $seg[0] - $lastSeg[0],
                        $seg[1] - $lastSeg[1],
                        $seg[2] - $lastSeg[2],
                        $seg[3] - $lastSeg[3]
                    ]);
                } else {
                    $encodedSegments[] = $vlq->encodeIntegers($seg);
                }

                $lastSeg = $seg;
            }

            $encodedLines[] = implode(",", $encodedSegments);
        }

        return implode(";", $encodedLines);
    }*/
}