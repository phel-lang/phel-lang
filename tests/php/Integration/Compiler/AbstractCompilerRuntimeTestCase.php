<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;
use function tempnam;

/**
 * Base for the end-to-end compiler cases that evaluate real Phel through a
 * `CompilerFacade`: they all need `phel.core` loaded into a fresh global
 * environment once per class, then the `user` namespace and the gensym counter
 * reset before each case.
 *
 * `Phel::bootstrap(__DIR__)` resolves against this file's directory, which is
 * the same directory the subclasses live in, so the auto-detected project
 * structure is identical to bootstrapping from each subclass.
 */
abstract class AbstractCompilerRuntimeTestCase extends TestCase
{
    protected static GlobalEnvironmentInterface $globalEnv;

    protected CompilerFacade $compilerFacade;

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
}
