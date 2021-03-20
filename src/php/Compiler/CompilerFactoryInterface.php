<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Compiler\Emitter\EmitterInterface;
use Phel\Compiler\Emitter\OutputEmitterInterface;
use Phel\Compiler\Lexer\LexerInterface;
use Phel\Compiler\Parser\ParserInterface;
use Phel\Compiler\Reader\ReaderInterface;

/**
 * deprecated Remove me without replacement. CompilerFacade is the one which should have the Interface!
 */
interface CompilerFactoryInterface
{
    public function createEvalCompiler(): EvalCompilerInterface;

    public function createFileCompiler(): FileCompilerInterface;

    public function createLexer(bool $withLocation = true): LexerInterface;

    public function createParser(): ParserInterface;

    public function createReader(): ReaderInterface;

    public function createEmitter(bool $enableSourceMaps = true): EmitterInterface;

    public function createOutputEmitter(bool $enableSourceMaps = true): OutputEmitterInterface;
}
