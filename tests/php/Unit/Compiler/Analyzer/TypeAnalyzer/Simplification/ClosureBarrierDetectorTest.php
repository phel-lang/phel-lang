<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TypeAnalyzer\Simplification;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayGetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArraySetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification\ClosureBarrierDetector;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

/**
 * A literal `fn` body spliced by the `update` lowering (#3322) runs in the
 * enclosing PHP scope instead of a closure that captured its locals by value.
 * Any write that could reach an enclosing local keeps the closure.
 */
final class ClosureBarrierDetectorTest extends TestCase
{
    private NodeEnvironmentInterface $env;

    protected function setUp(): void
    {
        $this->env = NodeEnvironment::empty()
            ->withMergedLocals([Symbol::create('arr')])
            ->withExpressionContext();
    }

    public function test_aset_on_an_enclosing_local(): void
    {
        self::assertTrue($this->needsClosure($this->aset($this->local('arr'))));
    }

    public function test_aset_through_an_aget_of_an_enclosing_local(): void
    {
        self::assertTrue($this->needsClosure($this->aset($this->aget($this->local('arr')))));
    }

    public function test_aset_on_a_body_local(): void
    {
        self::assertFalse($this->needsClosure($this->aset($this->local('own'))));
    }

    public function test_an_enclosing_local_passed_to_a_php_function(): void
    {
        self::assertTrue($this->needsClosure($this->phpCall('sort', $this->local('arr'))));
    }

    public function test_an_offset_of_an_enclosing_local_passed_to_a_php_function(): void
    {
        self::assertTrue($this->needsClosure($this->phpCall('sort', $this->aget($this->aget($this->local('arr'))))));
    }

    public function test_an_enclosing_local_inside_a_php_argument_expression(): void
    {
        // `(php/max 0.0 (php/- s dt))`: the argument is a temporary, nothing
        // can be written through it.
        $arg = new CallNode($this->env, new PhpVarNode($this->env, '-'), [$this->local('own'), $this->local('arr')]);

        self::assertFalse($this->needsClosure($this->phpCall('max', $arg)));
    }

    public function test_an_enclosing_local_passed_to_an_operator(): void
    {
        $node = new CallNode($this->env, new PhpVarNode($this->env, '+'), [$this->local('arr'), new LiteralNode($this->env, 1)]);

        self::assertFalse($this->needsClosure($node));
    }

    private function needsClosure(AbstractNode $node): bool
    {
        return new ClosureBarrierDetector()->needsClosure($node, $this->env);
    }

    private function local(string $name): LocalVarNode
    {
        return new LocalVarNode($this->env, Symbol::create($name));
    }

    private function aget(AbstractNode $target): PhpArrayGetNode
    {
        return new PhpArrayGetNode($this->env, $target, [new LiteralNode($this->env, 0)]);
    }

    private function aset(AbstractNode $target): PhpArraySetNode
    {
        return new PhpArraySetNode($this->env, $target, [new LiteralNode($this->env, 0)], new LiteralNode($this->env, 99));
    }

    private function phpCall(string $name, AbstractNode $arg): CallNode
    {
        return new CallNode($this->env, new PhpVarNode($this->env, $name), [$arg]);
    }
}
