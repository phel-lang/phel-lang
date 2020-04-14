<?php

namespace Phel;

use Phel\Exceptions\AnalyzerException;
use Phel\Exceptions\ReaderException;
use Phel\Stream\CharStream;
use Phel\Stream\FileCharStream;
use Throwable;

class Compiler {

    public function compileFile(string $filename, GlobalEnvironment $globalEnv) {
        $stream = new FileCharStream($filename);

        $this->compileStream($stream, $globalEnv);
    }

    public function compileStream(CharStream $stream, GlobalEnvironment $globalEnv) {
        $reader = new Reader();
        $analzyer = new Analyzer($globalEnv);
        $emitter = new Emitter();

        while (true) {
            try {
                $readerResult = $reader->read($stream);

                // If we reached the end exit this loop
                if (!$readerResult->getAst()) {
                    break;
                }

                try {
                    $nodes = $analzyer->analyze($readerResult->getAst());
                } catch (AnalyzerException $e) {
                    $this->printAnalyzeException($e, $readerResult);
                    exit;
                } catch (Throwable $e) {
                    echo $readerResult->getCode();
                    //var_dump($e);
                    throw $e;
                }

                $emitter->emitAndEval($nodes);

            } catch (ReaderException $e) {
                $e->__toString();
                exit;
            } catch (Throwable $e) {
                throw $e;
            }
        }
    }

    private function printAnalyzeException(AnalyzerException $e, ReaderResult $readerResult) {
        $firstLine = $e->getStartLocation() ? $e->getStartLocation()->getLine() : -1;

        echo $e->getMessage() . "\n";
        echo "in " . ($e->getStartLocation() ? $e->getStartLocation()->getFile() : 'unknown-file') . ':' . $firstLine . "\n\n";

        $lines = explode("\n", $readerResult->getCode());
        $endLineLength = strlen((string) $readerResult->getEndLocation()->getLine());
        $padLength = $endLineLength - strlen((string) $readerResult->getStartLocation()->getLine());
        foreach ($lines as $index => $line) {
            echo str_pad($firstLine + $index, $padLength, ' ', STR_PAD_LEFT);
            echo "| ";
            echo $line;
            echo "\n";

            if ($e->getStartLocation() && $e->getEndLocation() && $e->getStartLocation()->getLine() == $e->getEndLocation()->getLine()) {
                if ($e->getStartLocation()->getLine() == $index + $readerResult->getStartLocation()->getLine()) {
                    echo str_repeat(' ', $endLineLength + 1 + $e->getStartLocation()->getColumn());
                    echo str_repeat('^', $e->getEndLocation()->getColumn() - $e->getStartLocation()->getColumn() + 1);
                    echo "\n";
                }
            }
        }

        if ($e->getPrevious()) {
            echo "\n\nPhp Stack Trace:\n";
            echo $e->getPrevious()->getTraceAsString();
            echo "\n";
        }
    }
}