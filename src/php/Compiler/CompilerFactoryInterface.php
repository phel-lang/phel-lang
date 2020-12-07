<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Compiler\Emitter\OutputEmitterInterface;

interface CompilerFactoryInterface
{
    public function createEvalCompiler(GlobalEnvironmentInterface $globalEnv): EvalCompilerInterface;

    public function createFileCompiler(GlobalEnvironmentInterface $globalEnv): FileCompilerInterface;

    public function createLexer(): LexerInterface;

    public function createReader(GlobalEnvironmentInterface $globalEnv): ReaderInterface;

    public function createEmitter(bool $enableSourceMaps = true): EmitterInterface;

    public function createOutputEmitter(bool $enableSourceMaps = true): OutputEmitterInterface;
}
