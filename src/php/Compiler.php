<?php

namespace Phel;

use Phel\Exceptions\AnalyzerException;
use Phel\Exceptions\ExceptionPrinter;
use Phel\Exceptions\HtmlExceptionPrinter;
use Phel\Exceptions\ReaderException;
use Phel\Exceptions\TextExceptionPrinter;
use Phel\Stream\CharStream;
use Phel\Stream\FileCharStream;
use Throwable;

class Compiler {

    /**
     * @var ExceptionPrinter
     */
    private $exceptionPrinter;

    public function __construct()
    {
        if (php_sapi_name() == 'cli') {
            $this->exceptionPrinter = new TextExceptionPrinter();
        } else {
            $this->exceptionPrinter = new HtmlExceptionPrinter();
        }
    }

    public function compileFile(string $filename, GlobalEnvironment $globalEnv) {
        $stream = new FileCharStream($filename);

        return $this->compileStream($stream, $globalEnv);
    }

    public function compileStream(CharStream $stream, GlobalEnvironment $globalEnv) {
        $reader = new Reader();
        $analzyer = new Analyzer($globalEnv);
        $emitter = new Emitter();
        $code = '';

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
                    $this->exceptionPrinter->printException($e, $readerResult->getCodeSnippet());
                    exit;
                } catch (Throwable $e) {
                    echo $readerResult->getCodeSnippet()->getCode();
                    //var_dump($e);
                    throw $e;
                }

                $code .= $emitter->emitAndEval($nodes);

            } catch (ReaderException $e) {
                $this->exceptionPrinter->printException($e, $e->getCodeSnippet());
                exit;
            } catch (Throwable $e) {
                throw $e;
            }
        }

        return $code;
    }
}