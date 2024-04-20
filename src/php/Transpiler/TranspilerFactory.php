<?php

declare(strict_types=1);

namespace Phel\Transpiler;

use Gacela\Framework\AbstractFactory;
use Phel\Filesystem\FilesystemFacadeInterface;
use Phel\Printer\Printer;
use Phel\Transpiler\Domain\Analyzer\Analyzer;
use Phel\Transpiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Transpiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Transpiler\Domain\Compiler\CodeCompilerInterface;
use Phel\Transpiler\Domain\Compiler\CodeTranspiler;
use Phel\Transpiler\Domain\Compiler\EvalTranspiler;
use Phel\Transpiler\Domain\Compiler\EvalTranspilerInterface;
use Phel\Transpiler\Domain\Emitter\FileEmitter;
use Phel\Transpiler\Domain\Emitter\FileEmitterInterface;
use Phel\Transpiler\Domain\Emitter\OutputEmitter;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\MungeInterface;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitterFactory;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\OutputEmitterOptions;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\SourceMap\SourceMapGenerator;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\SourceMap\SourceMapState;
use Phel\Transpiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Transpiler\Domain\Emitter\StatementEmitter;
use Phel\Transpiler\Domain\Emitter\StatementEmitterInterface;
use Phel\Transpiler\Domain\Evaluator\EvaluatorInterface;
use Phel\Transpiler\Domain\Evaluator\RequireEvaluator;
use Phel\Transpiler\Domain\Lexer\Lexer;
use Phel\Transpiler\Domain\Lexer\LexerInterface;
use Phel\Transpiler\Domain\Parser\ExpressionParserFactory;
use Phel\Transpiler\Domain\Parser\Parser;
use Phel\Transpiler\Domain\Parser\ParserInterface;
use Phel\Transpiler\Domain\Reader\ExpressionReaderFactory;
use Phel\Transpiler\Domain\Reader\QuasiquoteTransformer;
use Phel\Transpiler\Domain\Reader\Reader;
use Phel\Transpiler\Domain\Reader\ReaderInterface;
use Phel\Transpiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Transpiler\Infrastructure\Munge;
use Phel\Transpiler\Infrastructure\TranspileOptions;

final class TranspilerFactory extends AbstractFactory
{
    public function createEvalTranspiler(): EvalTranspilerInterface
    {
        return new EvalTranspiler(
            $this->createLexer(),
            $this->createParser(),
            $this->createReader(),
            $this->createAnalyzer(),
            $this->createStatementEmitter(),
            $this->createEvaluator(),
        );
    }

    public function createCodeTranspiler(TranspileOptions $compileOptions): CodeCompilerInterface
    {
        return new CodeTranspiler(
            $this->createLexer(),
            $this->createParser(),
            $this->createReader(),
            $this->createAnalyzer(),
            $this->createStatementEmitter($compileOptions->isSourceMapsEnabled()),
            $this->createFileEmitter($compileOptions->isSourceMapsEnabled()),
            $this->createEvaluator(),
        );
    }

    public function createLexer(bool $withLocation = true): LexerInterface
    {
        return new Lexer($withLocation);
    }

    public function createReader(): ReaderInterface
    {
        return new Reader(
            new ExpressionReaderFactory(),
            new QuasiquoteTransformer($this->getGlobalEnvironment()),
        );
    }

    public function createParser(): ParserInterface
    {
        return new Parser(
            new ExpressionParserFactory(),
            $this->getGlobalEnvironment(),
        );
    }

    public function createAnalyzer(): AnalyzerInterface
    {
        return new Analyzer($this->getGlobalEnvironment());
    }

    public function createStatementEmitter(bool $enableSourceMaps = true): StatementEmitterInterface
    {
        return new StatementEmitter(
            new SourceMapGenerator(),
            $this->createOutputEmitter($enableSourceMaps),
        );
    }

    public function createFileEmitter(bool $enableSourceMaps = true): FileEmitterInterface
    {
        return new FileEmitter(
            new SourceMapGenerator(),
            new OutputEmitter(
                $enableSourceMaps,
                new NodeEmitterFactory(),
                $this->createMunge(),
                Printer::readable(),
                new SourceMapState(),
                new OutputEmitterOptions(OutputEmitterOptions::EMIT_MODE_FILE),
            ),
        );
    }

    public function createOutputEmitter(bool $enableSourceMaps = true): OutputEmitterInterface
    {
        return new OutputEmitter(
            $enableSourceMaps,
            new NodeEmitterFactory(),
            $this->createMunge(),
            Printer::readable(),
            new SourceMapState(),
            new OutputEmitterOptions(OutputEmitterOptions::EMIT_MODE_STATEMENT),
        );
    }

    public function createEvaluator(): EvaluatorInterface
    {
        return new RequireEvaluator(
            $this->getFilesystemFacade(),
        );
    }

    public function createMunge(): MungeInterface
    {
        return new Munge();
    }

    private function getFilesystemFacade(): FilesystemFacadeInterface
    {
        return $this->getProvidedDependency(TranspilerDependencyProvider::FACADE_FILESYSTEM);
    }

    private function getGlobalEnvironment(): GlobalEnvironmentInterface
    {
        if (!GlobalEnvironmentSingleton::isInitialized()) {
            return GlobalEnvironmentSingleton::initializeNew();
        }

        return GlobalEnvironmentSingleton::getInstance();
    }
}
