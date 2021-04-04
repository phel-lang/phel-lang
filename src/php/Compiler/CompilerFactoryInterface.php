<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Emitter\EmitterInterface;
use Phel\Compiler\Emitter\OutputEmitterInterface;
use Phel\Compiler\Lexer\LexerInterface;
use Phel\Compiler\Parser\ParserInterface;
use Phel\Compiler\Reader\ReaderInterface;

interface CompilerFactoryInterface
{
    public function createEvalCompiler(GlobalEnvironmentInterface $globalEnv): EvalCompilerInterface;

    public function createFileCompiler(GlobalEnvironmentInterface $globalEnv): FileCompilerInterface;

    public function createLexer(bool $withoutLocation = false): LexerInterface;

    public function createParser(): ParserInterface;

    public function createReader(GlobalEnvironmentInterface $globalEnv): ReaderInterface;

    public function createEmitter(bool $enableSourceMaps = true): EmitterInterface;

    public function createOutputEmitter(bool $enableSourceMaps = true): OutputEmitterInterface;
}
