<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\ForeachNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\QuoteNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\YieldDetector;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class YieldDetectorTest extends TestCase
{
    public function test_detects_a_direct_yield(): void
    {
        self::assertTrue($this->containsYield($this->yield('x')));
    }

    public function test_detects_yield_nested_in_wrapping_nodes(): void
    {
        $inner = new DoNode($this->env(), [], $this->yield('x'));
        $outer = new DoNode($this->env(), [$this->literal()], $inner);

        self::assertTrue($this->containsYield($outer));
    }

    public function test_detects_yield_in_a_foreach_body(): void
    {
        self::assertTrue($this->containsYield($this->foreach($this->yield('x'))));
    }

    public function test_a_call_that_is_not_yield_has_no_yield(): void
    {
        $call = new CallNode($this->env(), $this->phpVar('array_map'), [$this->local('x')]);

        self::assertFalse($this->containsYield($call));
    }

    public function test_an_unknown_leaf_node_has_no_yield(): void
    {
        self::assertFalse($this->containsYield($this->local('x')));
    }

    public function test_stops_at_a_closure_boundary(): void
    {
        $fn = $this->fn($this->yield('y'));
        $do = new DoNode($this->env(), [], $fn);

        self::assertFalse($this->containsYield($do));
    }

    public function test_a_foreach_whose_body_yields_only_inside_a_nested_fn_has_no_yield(): void
    {
        $call = new CallNode($this->env(), $this->fn($this->yield('y')), [$this->local('x')]);

        self::assertFalse($this->containsYield($this->foreach($call)));
    }

    public function test_detects_yield_in_a_set_literal(): void
    {
        self::assertTrue($this->containsYield(new SetNode($this->env(), [$this->yield('x')])));
    }

    public function test_fails_closed_on_an_unrecognised_node(): void
    {
        // A node shape the child map does not enumerate must be assumed to
        // possibly yield, so the wrapper is kept rather than wrongly elided.
        self::assertTrue($this->containsYield(new QuoteNode($this->env(), 'x')));
    }

    private function containsYield(AbstractNode $node): bool
    {
        return new YieldDetector()->containsYield($node);
    }

    private function yield(string $name): CallNode
    {
        return new CallNode($this->env(), $this->phpVar('yield'), [$this->local($name)]);
    }

    private function foreach(AbstractNode $bodyExpr): ForeachNode
    {
        return new ForeachNode($this->env(), $bodyExpr, $this->local('coll'), Symbol::create('x'));
    }

    private function fn(AbstractNode $body): FnNode
    {
        return new FnNode($this->env(), [], $body, [], false, false);
    }

    private function local(string $name): LocalVarNode
    {
        return new LocalVarNode($this->env(), Symbol::create($name));
    }

    private function phpVar(string $name): PhpVarNode
    {
        return new PhpVarNode($this->env(), $name);
    }

    private function literal(): LiteralNode
    {
        return new LiteralNode($this->env(), 0);
    }

    private function env(): NodeEnvironment
    {
        return NodeEnvironment::empty()->withExpressionContext();
    }
}
