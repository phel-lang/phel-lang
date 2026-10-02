<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Keyword;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;
use function tempnam;

/**
 * A long-lived process loads a namespace again from source while what it
 * loaded before is still defined: the REPL, `phel watch`, nREPL, or a run that
 * recompiles a file the compiled cache stopped serving. `phel.core` declares
 * `map`, `seq` and `second` ahead of their definitions, so a `declare` that
 * reset them to nil made every macro expanded before the real definition ran
 * again call nil.
 */
final class DeclareKeepsDefinitionTest extends TestCase
{
    private CompilerFacade $compilerFacade;

    protected function setUp(): void
    {
        Phel::bootstrap(__DIR__);
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();
        new BuildFacade()->compileFile(
            __DIR__ . '/../../../../src/phel/core.phel',
            (string) tempnam(sys_get_temp_dir(), 'phel-core'),
        );
        $this->compilerFacade = new CompilerFacade();
    }

    public function test_declare_keeps_the_value_of_a_symbol_defined_by_an_earlier_load(): void
    {
        // What a reload sees: the runtime still holds the definition, the
        // fresh analyzer environment compiling the namespace again does not.
        $helper = static fn(): string => 'defined';
        Registry::getInstance()->addDefinition('probe.declare_keeps', 'helper', $helper);

        $this->compilerFacade->eval("(ns probe.declare-keeps)\n(declare helper)");

        self::assertSame($helper, Registry::getInstance()->getDefinition('probe.declare_keeps', 'helper'));
    }

    public function test_declare_keeps_the_root_value_while_the_symbol_is_dynamically_bound(): void
    {
        Registry::getInstance()->addDefinition('probe.declare_dynamic', '*setting*', 'root', Phel::map(Keyword::create('dynamic'), true));

        Phel::openBindingFrame();
        Phel::setVar('probe.declare_dynamic', '*setting*', 'bound');
        Phel::commitAndRunBindingFrame(
            fn(): mixed => $this->compilerFacade->eval("(ns probe.declare-dynamic)\n(declare *setting*)"),
        );

        self::assertSame('root', Registry::readRoot('probe.declare_dynamic', '*setting*'));
    }

    public function test_declare_binds_nil_before_the_definition(): void
    {
        self::assertTrue($this->compilerFacade->eval("(ns probe.declare-fresh)\n(declare later)\n(nil? later)"));
    }
}
