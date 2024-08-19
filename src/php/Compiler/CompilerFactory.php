<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Gacela\Framework\AbstractFactory;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Application\CodeCompiler;
use Phel\Compiler\Application\EvalCompiler;
use Phel\Compiler\Application\Lexer;
use Phel\Compiler\Application\Munge;
use Phel\Compiler\Application\Parser;
use Phel\Compiler\Application\Reader;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Compiler\CodeCompilerInterface;
use Phel\Compiler\Domain\Compiler\EvalCompilerInterface;
use Phel\Compiler\Domain\Emitter\FileEmitter;
use Phel\Compiler\Domain\Emitter\FileEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\MungeInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterFactory;
use Phel\Compiler\Domain\Emitter\OutputEmitter\OutputEmitterOptions;
use Phel\Compiler\Domain\Emitter\OutputEmitter\SourceMap\SourceMapGenerator;
use Phel\Compiler\Domain\Emitter\OutputEmitter\SourceMap\SourceMapState;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Compiler\Domain\Emitter\StatementEmitter;
use Phel\Compiler\Domain\Emitter\StatementEmitterInterface;
use Phel\Compiler\Domain\Evaluator\EvaluatorInterface;
use Phel\Compiler\Domain\Evaluator\RequireEvaluator;
use Phel\Compiler\Domain\Lexer\LexerInterface;
use Phel\Compiler\Domain\Parser\ExpressionParserFactory;
use Phel\Compiler\Domain\Parser\ParserInterface;
use Phel\Compiler\Domain\Reader\ExpressionReaderFactory;
use Phel\Compiler\Domain\Reader\QuasiquoteTransformer;
use Phel\Compiler\Domain\Reader\ReaderInterface;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Filesystem\FilesystemFacadeInterface;
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
            $this->createEvaluator(),
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
        return $this->getProvidedDependency(CompilerProvider::FACADE_FILESYSTEM);
    }

    private function getGlobalEnvironment(): GlobalEnvironmentInterface
    {
        if (!GlobalEnvironmentSingleton::isInitialized()) {
            return GlobalEnvironmentSingleton::initializeNew();
        }

        return GlobalEnvironmentSingleton::getInstance();
    }
}
