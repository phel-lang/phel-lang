<?php

namespace Phel;

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

                $nodes = $analzyer->analyze($readerResult->getAst());

                $emitter->emitAndEval($nodes);

            } catch (ReaderException $e) {
                $e->__toString();
                exit;
            } catch (Throwable $e) {
                throw $e;
            }
        }
    }
}