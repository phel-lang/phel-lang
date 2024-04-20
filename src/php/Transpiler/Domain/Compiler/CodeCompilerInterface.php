<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Compiler;

use Phel\Lang\TypeInterface;
use Phel\Transpiler\Domain\Emitter\EmitterResult;
use Phel\Transpiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Transpiler\Domain\Evaluator\Exceptions\TrarnspiledCodeIsMalformedException;
use Phel\Transpiler\Domain\Exceptions\TranspilerException;
use Phel\Transpiler\Infrastructure\TranspileOptions;

interface CodeCompilerInterface
{
    /**
     * @throws TranspilerException
     * @throws TrarnspiledCodeIsMalformedException
     * @throws FileException
     */
    public function compileString(string $phelCode, TranspileOptions $compileOptions): EmitterResult;

    /**
     * @param TypeInterface|string|float|int|bool|null $form The phel form to evaluate
     *
     * @throws TranspilerException
     * @throws TrarnspiledCodeIsMalformedException
     * @throws FileException
     */
    public function compileForm(float|bool|int|string|TypeInterface|null $form, TranspileOptions $compileOptions): EmitterResult;
}
