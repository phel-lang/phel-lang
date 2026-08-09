<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter;

use Iterator;
use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\CallSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitter\ConstructorSpecialization;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConstructorSpecializationTest extends TestCase
{
    /**
     * @return Iterator<int<0, max>, array{string, string}>
     */
    public static function providerConstructors(): Iterator
    {
        yield ['list', 'list'];
        yield ['vector', 'vector'];
        yield ['queue', 'queue'];
        yield ['hash-map', 'map'];
        yield ['array-map', 'map'];
    }

    #[DataProvider('providerConstructors')]
    public function test_core_constructor_lowers_to_its_php_factory(string $phelName, string $factory): void
    {
        $node = $this->coreCall($phelName, [$this->literal(1)]);

        self::assertSame($factory, ConstructorSpecialization::factoryMethod($node));
    }

    /**
     * The lowering is justified by the callee alone, so every argument count
     * qualifies, including zero and counts well past any fixed arity.
     */
    public function test_every_argument_count_qualifies(): void
    {
        foreach ([0, 1, 2, 3, 7] as $argc) {
            $args = array_fill(0, $argc, $this->literal(1));

            self::assertSame(
                'list',
                ConstructorSpecialization::factoryMethod($this->coreCall('list', $args)),
                'argument count ' . $argc,
            );
        }
    }

    /**
     * An odd count is an error for the map constructors, and it must stay an
     * error raised by the constructor rather than one raised by dispatch.
     * `\Phel::map([1])` throws the same "even number of elements" the runtime
     * fn reaches, so the specialisation deliberately does not filter on parity.
     */
    public function test_odd_argument_count_still_lowers_for_the_map_constructors(): void
    {
        $node = $this->coreCall('hash-map', [$this->literal(1)]);

        self::assertSame('map', ConstructorSpecialization::factoryMethod($node));
    }

    /**
     * `(let [list ...] (list 1))` binds a local, which analyses to a
     * `LocalVarNode` rather than a `GlobalVarNode`, so the call must fall
     * through to the local instead of being rewritten to `\Phel::list`.
     */
    public function test_locally_shadowed_constructor_falls_back(): void
    {
        $env = $this->env();
        $node = new CallNode($env, new LocalVarNode($env, Symbol::create('list')), [$this->literal(1)]);

        self::assertNull(ConstructorSpecialization::factoryMethod($node));
        self::assertFalse(ConstructorSpecialization::isConstructorCall($node));
    }

    public function test_same_name_in_another_namespace_falls_back(): void
    {
        $env = $this->env();
        $node = new CallNode(
            $env,
            new GlobalVarNode($env, 'my-app.core', Symbol::create('list'), Phel::map()),
            [$this->literal(1)],
        );

        self::assertNull(ConstructorSpecialization::factoryMethod($node));
    }

    public function test_other_core_fn_falls_back(): void
    {
        self::assertNull(ConstructorSpecialization::factoryMethod($this->coreCall('conj', [$this->literal(1)])));
    }

    /**
     * The call cache scanner reserves a `static $__phel_call_N` slot for every
     * call it does not know is specialised. Missing this registration leaves
     * orphan slots in the emitted PHP.
     */
    public function test_is_registered_with_the_shared_specialisation_predicate(): void
    {
        self::assertTrue(CallSpecialization::isSpecialized($this->coreCall('list', [$this->literal(1)])));
    }

    /**
     * @param list<AbstractNode> $args
     */
    private function coreCall(string $name, array $args): CallNode
    {
        return new CallNode(
            $this->env(),
            new GlobalVarNode($this->env(), CompilerConstants::PHEL_CORE_NAMESPACE, Symbol::create($name), Phel::map()),
            $args,
        );
    }

    private function literal(mixed $value): LiteralNode
    {
        return new LiteralNode($this->env(), $value);
    }

    private function env(): NodeEnvironment
    {
        return NodeEnvironment::empty()->withExpressionContext();
    }
}
