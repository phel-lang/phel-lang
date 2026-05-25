<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\DuplicateDefinitionException;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Shared\CompileOptions;
use PHPUnit\Framework\TestCase;

final class DefonceRedefineRuntimeTest extends TestCase
{
    private static GlobalEnvironmentInterface $globalEnv;

    private CompilerFacade $compilerFacade;

    public static function setUpBeforeClass(): void
    {
        Phel::bootstrap(__DIR__);
        Symbol::resetGen();
        $globalEnv = GlobalEnvironmentSingleton::initializeNew();
        new BuildFacade()->compileFile(
            __DIR__ . '/../../../../src/phel/core.phel',
            tempnam(sys_get_temp_dir(), 'phel-core'),
        );
        self::$globalEnv = $globalEnv;
    }

    protected function setUp(): void
    {
        $this->compilerFacade = new CompilerFacade();
        self::$globalEnv->setNs('user');
        Symbol::resetGen();
    }

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
