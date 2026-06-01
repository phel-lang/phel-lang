<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NilAndBooleanCheckSpecialization;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\TestCase;

final class NilAndBooleanCheckSpecializationTest extends TestCase
{
    public function test_unary_nil_check_is_recognised(): void
    {
        self::assertTrue(NilAndBooleanCheckSpecialization::isNilCheck($this->coreCall('nil?', [$this->local('x')])));
    }

    public function test_each_predicate_recognises_only_its_own_name(): void
    {
        $arg = [$this->local('x')];

        self::assertTrue(NilAndBooleanCheckSpecialization::isSomeCheck($this->coreCall('some?', $arg)));
        self::assertTrue(NilAndBooleanCheckSpecialization::isTrueCheck($this->coreCall('true?', $arg)));
        self::assertTrue(NilAndBooleanCheckSpecialization::isFalseCheck($this->coreCall('false?', $arg)));
        self::assertTrue(NilAndBooleanCheckSpecialization::isTruthyCheck($this->coreCall('truthy?', $arg)));

        self::assertFalse(NilAndBooleanCheckSpecialization::isNilCheck($this->coreCall('some?', $arg)));
    }

    public function test_two_arg_call_is_not_specialised(): void
    {
        // `(some? pred coll)` overload — different shape, must not specialise.
        $node = $this->coreCall('some?', [$this->local('pred'), $this->local('coll')]);

        self::assertFalse(NilAndBooleanCheckSpecialization::isSomeCheck($node));
    }

    public function test_zero_arg_call_is_not_specialised(): void
    {
        $node = $this->coreCall('nil?', []);

        self::assertFalse(NilAndBooleanCheckSpecialization::isNilCheck($node));
    }

    public function test_non_core_namespace_is_not_specialised(): void
    {
        $node = new CallNode(
            $this->env(),
            new GlobalVarNode($this->env(), 'my\\app', Symbol::create('nil?'), Phel::map()),
            [$this->local('x')],
        );

        self::assertFalse(NilAndBooleanCheckSpecialization::isNilCheck($node));
    }

    public function test_local_var_callee_is_not_specialised(): void
    {
        $node = new CallNode($this->env(), $this->local('nil?'), [$this->local('x')]);

        self::assertFalse(NilAndBooleanCheckSpecialization::isNilCheck($node));
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
