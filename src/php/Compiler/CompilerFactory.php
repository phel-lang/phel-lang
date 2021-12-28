<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Gacela\Framework\AbstractFactory;
use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentSingleton;
use Phel\Compiler\Compiler\CodeCompiler;
use Phel\Compiler\Compiler\CodeCompilerInterface;
use Phel\Compiler\Compiler\CompileOptions;
use Phel\Compiler\Compiler\EvalCompiler;
use Phel\Compiler\Compiler\EvalCompilerInterface;
use Phel\Compiler\Emitter\FileEmitter;
use Phel\Compiler\Emitter\FileEmitterInterface;
use Phel\Compiler\Emitter\OutputEmitter;
use Phel\Compiler\Emitter\OutputEmitter\Munge;
use Phel\Compiler\Emitter\OutputEmitter\MungeInterface;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterFactory;
use Phel\Compiler\Emitter\OutputEmitter\OutputEmitterOptions;
use Phel\Compiler\Emitter\OutputEmitter\SourceMap\SourceMapGenerator;
use Phel\Compiler\Emitter\OutputEmitter\SourceMap\SourceMapState;
use Phel\Compiler\Emitter\OutputEmitterInterface;
use Phel\Compiler\Emitter\StatementEmitter;
use Phel\Compiler\Emitter\StatementEmitterInterface;
use Phel\Compiler\Evaluator\EvaluatorInterface;
use Phel\Compiler\Evaluator\RequireEvaluator;
use Phel\Compiler\Lexer\Lexer;
use Phel\Compiler\Lexer\LexerInterface;
use Phel\Compiler\Parser\ExpressionParserFactory;
use Phel\Compiler\Parser\Parser;
use Phel\Compiler\Parser\ParserInterface;
use Phel\Compiler\Reader\ExpressionReaderFactory;
use Phel\Compiler\Reader\QuasiquoteTransformer;
use Phel\Compiler\Reader\Reader;
use Phel\Compiler\Reader\ReaderInterface;
use Phel\Printer\Printer;

final class CompilerFactory extends AbstractFactory
{
    public function createEvalCompiler(): EvalCompilerInterface
    {
        return new EvalCompiler(
            $this->createLexer(),
            $this->createParser(),
            $this->createReader(),
            $this->createAnalyzer(),
            $this->createStatementEmitter(),
            $this->createEvaluator()
        );
    }

    public function createCodeCompiler(CompileOptions $compileOptions): CodeCompilerInterface
    {
        return new CodeCompiler(
            $this->createLexer(),
            $this->createParser(),
            $this->createReader(),
            $this->createAnalyzer(),
            $this->createStatementEmitter($compileOptions->isSourceMapsEnabled()),
            $this->createFileEmitter($compileOptions->isSourceMapsEnabled()),
            $this->createEvaluator()
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
            new QuasiquoteTransformer($this->getGlobalEnvironment())
        );
    }

    public function createParser(): ParserInterface
    {
        return new Parser(
            new ExpressionParserFactory(),
            $this->getGlobalEnvironment()
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
            $this->createOutputEmitter($enableSourceMaps)
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
                new OutputEmitterOptions(OutputEmitterOptions::EMIT_MODE_FILE)
            )
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
            new OutputEmitterOptions(OutputEmitterOptions::EMIT_MODE_STATEMENT)
        );
    }

    public function createEvaluator(): EvaluatorInterface
    {
        return new RequireEvaluator();
    }

    public function createMunge(): MungeInterface
    {
        return new Munge();
    }

    private function getGlobalEnvironment(): GlobalEnvironmentInterface
    {
        if (!GlobalEnvironmentSingleton::isInitialized()) {
            return GlobalEnvironmentSingleton::initializeNew();
        }
        return GlobalEnvironmentSingleton::getInstance();
    }
}
