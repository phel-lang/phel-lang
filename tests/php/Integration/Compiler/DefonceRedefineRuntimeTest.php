<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel;
use Phel\Compiler\Domain\Analyzer\Exceptions\DuplicateDefinitionException;
use Phel\Shared\CompileOptions;

final class DefonceRedefineRuntimeTest extends AbstractCompilerRuntimeTestCase
{
    public function test_defonce_same_file_redefinition_is_idempotent(): void
    {
        $this->compilerFacade->compile(
            '(defonce redefine-target 1) (defonce redefine-target 2)',
            new CompileOptions(),
        );

        self::assertSame(1, Phel::getDefinition('user', 'redefine-target'));
    }

    public function test_def_after_def_still_throws(): void
    {
        $this->expectException(DuplicateDefinitionException::class);
        $this->expectExceptionMessage('Symbol def-target is already bound in namespace user');

        $this->compilerFacade->compile(
            '(def def-target 1) (def def-target 2)',
            new CompileOptions(),
        );
    }
}
