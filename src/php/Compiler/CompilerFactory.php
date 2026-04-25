<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Gacela\Framework\AbstractFactory;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Application\CodeCompiler;
use Phel\Compiler\Application\EvalCompiler;
use Phel\Compiler\Application\GlobalEnvironmentManager;
use Phel\Compiler\Application\Lexer;
use Phel\Compiler\Application\MacroExpander;
use Phel\Compiler\Application\Munge;
use Phel\Compiler\Application\NamespaceEnvironmentSerializer;
use Phel\Compiler\Application\ParenthesesChecker;
use Phel\Compiler\Application\Parser;
use Phel\Compiler\Application\Reader;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Environment\BackslashSeparatorDeprecator;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Compiler\CodeCompilerInterface;
use Phel\Compiler\Domain\Compiler\EvalCompilerInterface;
use Phel\Compiler\Domain\Emitter\FileEmitter;
use Phel\Compiler\Domain\Emitter\FileEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\EmitMode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\MungeInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterFactory;
use Phel\Compiler\Domain\Emitter\OutputEmitter\OutputEmitterOptions;
use Phel\Compiler\Domain\Emitter\OutputEmitter\SourceMap\SourceMapGenerator;
use Phel\Compiler\Domain\Emitter\OutputEmitter\SourceMap\SourceMapState;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Compiler\Domain\Emitter\StatementEmitter;
use Phel\Compiler\Domain\Emitter\StatementEmitterInterface;
use Phel\Compiler\Domain\Evaluator\EvaluatorInterface;
use Phel\Compiler\Domain\Evaluator\InMemoryEvaluator;
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
use Phel\Lang\TagHandlers\BuiltinTagHandlers;
use Phel\Lang\TagRegistry;
use Phel\Printer\Printer;

/**
 * @extends AbstractFactory<CompilerConfig>
 */
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
            new InMemoryEvaluator(),
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

    public function createCodeCompilerForCache(CompileOptions $compileOptions): CodeCompilerInterface
    {
        return new CodeCompiler(
            $this->createLexer(),
            $this->createParser(),
            $this->createReader(),
            $this->createAnalyzer(),
            $this->createStatementEmitter($compileOptions->isSourceMapsEnabled()),
            $this->createFileEmitterForCache($compileOptions->isSourceMapsEnabled()),
            $this->createEvaluator(),
        );
    }

    public function createLexer(bool $withLocation = true): LexerInterface
    {
        return new Lexer($withLocation);
    }

    public function createReader(): ReaderInterface
    {
        BuiltinTagHandlers::registerAll(TagRegistry::getInstance());

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
        if ($this->getConfig()->warnDeprecationsEnabled()) {
            BackslashSeparatorDeprecator::enable();
        }

        return new Analyzer(
            $this->getGlobalEnvironment(),
            $this->getConfig()->assertsEnabled(),
        );
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
            $this->createOutputEmitterWithMode(EmitMode::File, $enableSourceMaps),
        );
    }

    public function createOutputEmitter(bool $enableSourceMaps = true): OutputEmitterInterface
    {
        return $this->createOutputEmitterWithMode(EmitMode::Statement, $enableSourceMaps);
    }

    public function createEvaluator(): EvaluatorInterface
    {
        return new RequireEvaluator(
            $this->getFilesystemFacade(),
        );
    }

    public function createNamespaceEnvironmentSerializer(): NamespaceEnvironmentSerializer
    {
        return new NamespaceEnvironmentSerializer(
            $this->getGlobalEnvironment(),
        );
    }

    public function createMunge(): MungeInterface
    {
        return new Munge();
    }

    public function createMacroExpander(): MacroExpander
    {
        return new MacroExpander(
            $this->getGlobalEnvironment(),
        );
    }

    public function createParenthesesChecker(): ParenthesesChecker
    {
        return new ParenthesesChecker();
    }

    public function createGlobalEnvironmentManager(): GlobalEnvironmentManager
    {
        return new GlobalEnvironmentManager();
    }

    private function createFileEmitterForCache(bool $enableSourceMaps = false): FileEmitterInterface
    {
        return new FileEmitter(
            new SourceMapGenerator(),
            $this->createOutputEmitterWithMode(EmitMode::Cache, $enableSourceMaps),
        );
    }

    private function createOutputEmitterWithMode(EmitMode $emitMode, bool $enableSourceMaps): OutputEmitter
    {
        return new OutputEmitter(
            $enableSourceMaps,
            new NodeEmitterFactory(),
            $this->createMunge(),
            Printer::readable(),
            new SourceMapState(),
            new OutputEmitterOptions($emitMode),
        );
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
