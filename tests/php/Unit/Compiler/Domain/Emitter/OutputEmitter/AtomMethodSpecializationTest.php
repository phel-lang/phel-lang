<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\AtomMethodSpecialization;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\TestCase;

final class AtomMethodSpecializationTest extends TestCase
{
    public function test_unary_deref_maps_to_deref_method(): void
    {
        $node = $this->coreCall('deref', [$this->local('a')]);

        self::assertSame(['deref', []], AtomMethodSpecialization::atomMethodCall($node));
        self::assertTrue(AtomMethodSpecialization::isAtomMethodCall($node));
    }

    public function test_binary_reset_maps_to_set_method_with_value_arg(): void
    {
        $node = $this->coreCall('reset!', [$this->local('a'), $this->local('v')]);

        self::assertSame(['set', [1]], AtomMethodSpecialization::atomMethodCall($node));
        self::assertTrue(AtomMethodSpecialization::isAtomMethodCall($node));
    }

    public function test_three_arg_deref_timeout_overload_is_not_specialised(): void
    {
        $node = $this->coreCall('deref', [$this->local('a'), $this->local('t'), $this->local('d')]);

        self::assertNull(AtomMethodSpecialization::atomMethodCall($node));
    }

    public function test_unary_reset_is_not_specialised(): void
    {
        $node = $this->coreCall('reset!', [$this->local('a')]);

        self::assertNull(AtomMethodSpecialization::atomMethodCall($node));
    }

    public function test_other_core_fn_is_not_specialised(): void
    {
        $node = $this->coreCall('inc', [$this->local('a')]);

        self::assertNull(AtomMethodSpecialization::atomMethodCall($node));
    }

    public function test_non_core_namespace_is_not_specialised(): void
    {
        $node = new CallNode(
            $this->env(),
            new GlobalVarNode($this->env(), 'my\\app', Symbol::create('deref'), Phel::map()),
            [$this->local('a')],
        );

        self::assertNull(AtomMethodSpecialization::atomMethodCall($node));
    }

    public function test_local_var_callee_is_not_specialised(): void
    {
        $node = new CallNode($this->env(), $this->local('deref'), [$this->local('a')]);

        self::assertNull(AtomMethodSpecialization::atomMethodCall($node));
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

    private function local(string $name): LocalVarNode
    {
        return new LocalVarNode($this->env(), Symbol::create($name));
    }

    private function env(): NodeEnvironment
    {
        return NodeEnvironment::empty()->withExpressionContext();
    }
}
