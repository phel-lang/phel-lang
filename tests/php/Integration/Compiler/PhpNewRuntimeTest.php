<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Evaluator\Exceptions\EvaluatedCodeException;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class PhpNewRuntimeTest extends TestCase
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

    public function test_dynamic_new_with_integer_literal_throws_descriptive_error(): void
    {
        $this->expectException(EvaluatedCodeException::class);
        $this->expectExceptionMessage('php/new expects a class name or object, int given (1)');

        $this->compilerFacade->eval(
            '(php/new 1)',
            new CompileOptions(),
        );
    }

    public function test_dynamic_new_with_bound_integer_throws_descriptive_error(): void
    {
        $this->expectException(EvaluatedCodeException::class);
        $this->expectExceptionMessage('php/new expects a class name or object, int given (42)');

        $this->compilerFacade->eval(
            '(let [x 42] (php/new x))',
            new CompileOptions(),
        );
    }
}
