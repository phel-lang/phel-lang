<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Emitter\Emitter;
use Phel\Compiler\Emitter\EmitterInterface;
use Phel\Compiler\Emitter\EvalEmitter;
use Phel\Compiler\Emitter\EvalEmitterInterface;
use Phel\Compiler\Emitter\OutputEmitter;
use Phel\Compiler\Emitter\OutputEmitter\Munge;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterFactory;
use Phel\Compiler\Emitter\OutputEmitter\SourceMap\SourceMapGenerator;
use Phel\Compiler\Emitter\OutputEmitterInterface;
use Phel\Compiler\Parser\Parser;
use Phel\Compiler\Parser\Parser\ExpressionParserFactory;
use Phel\Compiler\Parser\ParserInterface;
use Phel\Compiler\Parser\Reader;
use Phel\Compiler\Parser\Reader\ExpressionReaderFactory;
use Phel\Compiler\Parser\Reader\QuasiquoteTransformer;
use Phel\Compiler\Parser\ReaderInterface;

final class CompilerFactory implements CompilerFactoryInterface
{
    public function createEvalCompiler(GlobalEnvironmentInterface $globalEnv): EvalCompilerInterface
    {
        return new EvalCompiler(
            $this->createLexer(),
            $this->createParser(),
            $this->createReader($globalEnv),
            $this->createAnalyzer($globalEnv),
            $this->createEmitter()
        );
    }

    public function createFileCompiler(GlobalEnvironmentInterface $globalEnv): FileCompilerInterface
    {
        return new FileCompiler(
            $this->createLexer(),
            $this->createParser(),
            $this->createReader($globalEnv),
            $this->createAnalyzer($globalEnv),
            $this->createEmitter()
        );
    }

    public function createLexer(): LexerInterface
    {
        return new Lexer();
    }

    public function createReader(GlobalEnvironmentInterface $globalEnv): ReaderInterface
    {
        return new Reader(
            new ExpressionReaderFactory(),
            new QuasiquoteTransformer($globalEnv)
        );
    }

    public function createParser(): ParserInterface
    {
        return new Parser(
            new ExpressionParserFactory()
        );
    }

    public function createAnalyzer(GlobalEnvironmentInterface $globalEnv): AnalyzerInterface
    {
        return new Analyzer($globalEnv);
    }

    public function createEmitter(bool $enableSourceMaps = true): EmitterInterface
    {
        return new Emitter(
            $this->createOutputEmitter($enableSourceMaps),
            $this->createEvalEmitter()
        );
    }

    public function createOutputEmitter(bool $enableSourceMaps = true): OutputEmitterInterface
    {
        return new OutputEmitter(
            $enableSourceMaps,
            new SourceMapGenerator(),
            new NodeEmitterFactory(),
            new Munge()
        );
    }

    private function createEvalEmitter(): EvalEmitterInterface
    {
        return new EvalEmitter();
    }
}
