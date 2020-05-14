<?php

namespace Phel;

use Phel\Exceptions\AnalyzerException;
use Phel\Exceptions\ExceptionPrinter;
use Phel\Exceptions\HtmlExceptionPrinter;
use Phel\Exceptions\ReaderException;
use Phel\Exceptions\TextExceptionPrinter;
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

    public function compileFile(string $filename, GlobalEnvironment $globalEnv): string {
        return $this->compileString(file_get_contents($filename), $globalEnv, $filename);
    }

    public function compileString(string $code, GlobalEnvironment $globalEnv, string $source = 'string'): string {
        $lexer = new Lexer();
        $reader = new Reader();
        $analzyer = new Analyzer($globalEnv);
        $emitter = new Emitter();
        $tokenStream = $lexer->lexString($code, $source);
        $code = '';

        while (true) {
            try {
                $readerResult = $reader->readNext($tokenStream);

                // If we reached the end exit this loop
                if (!$readerResult) {
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