<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Compiler\Emitter\EvalEmitter;
use Phel\Compiler\Emitter\EvalEmitterInterface;
use Phel\Compiler\Emitter\OutputEmitter;
use Phel\Compiler\Emitter\OutputEmitter\Munge;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterFactory;
use Phel\Compiler\Emitter\OutputEmitter\SourceMap\SourceMapGenerator;
use Phel\Compiler\Emitter\OutputEmitterInterface;

final class CompilerFactory
{
    public function createEvalCompiler(GlobalEnvironmentInterface $globalEnv): EvalCompilerInterface
    {
        return new EvalCompiler(
            $this->createLexer(),
            $this->createReader($globalEnv),
            $this->createAnalyzer($globalEnv),
            $this->createEmitter()
        );
    }

    public function createFileCompiler(GlobalEnvironmentInterface $globalEnv): FileCompilerInterface
    {
        return new FileCompiler(
            $this->createLexer(),
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
            new QuasiquoteTransformer($globalEnv)
        );
    }

    private function createAnalyzer(GlobalEnvironmentInterface $globalEnv): AnalyzerInterface
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
