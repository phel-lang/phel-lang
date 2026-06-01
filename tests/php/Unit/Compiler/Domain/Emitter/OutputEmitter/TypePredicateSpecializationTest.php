<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\TypePredicateSpecialization;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\TestCase;

final class TypePredicateSpecializationTest extends TestCase
{
    public function test_int_predicate_lowers_to_is_int(): void
    {
        $node = $this->coreCall('int?', [$this->local('x')]);

        self::assertSame('is_int(%s)', TypePredicateSpecialization::typePredicateFragment($node));
        self::assertTrue(TypePredicateSpecialization::isTypePredicate($node));
    }

    public function test_keyword_predicate_lowers_to_instanceof(): void
    {
        $node = $this->coreCall('keyword?', [$this->local('x')]);

        self::assertSame('(%s instanceof \\Phel\\Lang\\Keyword)', TypePredicateSpecialization::typePredicateFragment($node));
    }

    public function test_non_predicate_core_fn_is_not_a_type_predicate(): void
    {
        $node = $this->coreCall('inc', [$this->local('x')]);

        self::assertNull(TypePredicateSpecialization::typePredicateFragment($node));
        self::assertFalse(TypePredicateSpecialization::isTypePredicate($node));
    }

    public function test_two_arg_predicate_is_not_specialised(): void
    {
        $env = $this->env();
        $node = $this->coreCall('int?', [$this->local('x'), new LiteralNode($env, 1)]);

        self::assertNull(TypePredicateSpecialization::typePredicateFragment($node));
    }

    public function test_numeric_predicate_on_int_tagged_local(): void
    {
        $node = $this->coreCall('zero?', [$this->localWithTag('n', 'int')]);

        self::assertSame('zero?', TypePredicateSpecialization::isNumericPredicate($node));
    }

    public function test_numeric_predicate_on_float_tagged_local(): void
    {
        $node = $this->coreCall('pos?', [$this->localWithTag('n', 'float')]);

        self::assertSame('pos?', TypePredicateSpecialization::isNumericPredicate($node));
    }

    public function test_numeric_predicate_on_untyped_local_falls_back(): void
    {
        $node = $this->coreCall('neg?', [$this->local('n')]);

        self::assertNull(TypePredicateSpecialization::isNumericPredicate($node));
    }

    public function test_numeric_predicate_on_non_numeric_tag_falls_back(): void
    {
        $node = $this->coreCall('zero?', [$this->localWithTag('n', 'string')]);

        self::assertNull(TypePredicateSpecialization::isNumericPredicate($node));
    }

    public function test_non_core_namespace_predicate_is_not_specialised(): void
    {
        $node = new CallNode(
            $this->env(),
            new GlobalVarNode($this->env(), 'my\\app', Symbol::create('int?'), Phel::map()),
            [$this->local('x')],
        );

        self::assertNull(TypePredicateSpecialization::typePredicateFragment($node));
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

    private function localWithTag(string $name, string $tag): LocalVarNode
    {
        $sym = Symbol::create($name);
        $meta = Phel::map(Keyword::create('tag'), $tag);
        $locals = [$sym->withMeta($meta)];

        $env = NodeEnvironment::empty()
            ->withExpressionContext()
            ->withMergedLocals($locals);

        return new LocalVarNode($env, $sym);
    }

    private function env(): NodeEnvironment
    {
        return NodeEnvironment::empty()->withExpressionContext();
    }
}
