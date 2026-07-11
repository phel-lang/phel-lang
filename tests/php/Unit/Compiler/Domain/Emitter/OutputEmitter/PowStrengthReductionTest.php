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
use Phel\Compiler\Domain\Emitter\OutputEmitter\NumericOperationSpecialization;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\TestCase;

final class PowStrengthReductionTest extends TestCase
{
    public function test_square_of_typed_int_local_is_reduced(): void
    {
        $base = $this->localWithTag('x', 'int');
        $node = $this->coreCall('**', [$base, $this->int(2)]);

        self::assertSame($base, NumericOperationSpecialization::squaredBase($node));
    }

    public function test_square_of_typed_float_local_is_reduced(): void
    {
        $base = $this->localWithTag('x', 'float');
        $node = $this->coreCall('**', [$base, $this->int(2)]);

        self::assertSame($base, NumericOperationSpecialization::squaredBase($node));
    }

    public function test_untyped_base_falls_back(): void
    {
        // Without a tag the base could be a Ratio or BigDecimal, whose
        // `power` and `multiply` runtime paths are not interchangeable.
        $node = $this->coreCall('**', [
            new LocalVarNode($this->env(), Symbol::create('x')),
            $this->int(2),
        ]);

        self::assertNull(NumericOperationSpecialization::squaredBase($node));
    }

    public function test_non_literal_exponent_falls_back(): void
    {
        $node = $this->coreCall('**', [
            $this->localWithTag('x', 'int'),
            $this->localWithTag('n', 'int'),
        ]);

        self::assertNull(NumericOperationSpecialization::squaredBase($node));
    }

    public function test_exponent_other_than_two_falls_back(): void
    {
        foreach ([0, 1, 3, -2] as $exp) {
            $node = $this->coreCall('**', [$this->localWithTag('x', 'int'), $this->int($exp)]);

            self::assertNull(
                NumericOperationSpecialization::squaredBase($node),
                'exponent ' . $exp . ' must not be reduced',
            );
        }
    }

    public function test_float_exponent_two_falls_back(): void
    {
        // `(** x 2.0)` takes the float branch of `power` even for an int base,
        // so it is not the same operation as an int square.
        $node = $this->coreCall('**', [
            $this->localWithTag('x', 'int'),
            new LiteralNode($this->env(), 2.0),
        ]);

        self::assertNull(NumericOperationSpecialization::squaredBase($node));
    }

    public function test_non_local_base_falls_back(): void
    {
        // The base is emitted twice, so it must be a side-effect-free variable.
        $inner = $this->coreCall('rand', [$this->int(1)]);
        $node = $this->coreCall('**', [$inner, $this->int(2)]);

        self::assertNull(NumericOperationSpecialization::squaredBase($node));
    }

    public function test_other_core_fn_falls_back(): void
    {
        $node = $this->coreCall('*', [$this->localWithTag('x', 'int'), $this->int(2)]);

        self::assertNull(NumericOperationSpecialization::squaredBase($node));
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

    private function int(int $value): LiteralNode
    {
        return new LiteralNode($this->env(), $value);
    }

    private function localWithTag(string $name, string $tag): LocalVarNode
    {
        $sym = Symbol::create($name);
        $locals = [$sym->withMeta(Phel::map(Keyword::create('tag'), $tag))];

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
