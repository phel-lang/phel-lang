<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpNamedArgNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpRefNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\ByRefLocalCollector;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class ByRefLocalCollectorTest extends TestCase
{
    public function test_collects_a_direct_php_ref_node(): void
    {
        self::assertSame(['x'], $this->collect($this->ref('x')));
    }

    public function test_collects_php_ref_from_a_call_argument(): void
    {
        $call = new CallNode($this->env(), $this->phpVar('preg_match'), [$this->ref('m')]);

        self::assertSame(['m'], $this->collect($call));
    }

    public function test_descends_through_nested_wrapping_nodes(): void
    {
        $inner = new DoNode($this->env(), [], new CallNode($this->env(), $this->phpVar('sort'), [$this->ref('m')]));
        $outer = new DoNode($this->env(), [$this->literal()], $inner);

        self::assertSame(['m'], $this->collect($outer));
    }

    public function test_collects_php_ref_from_a_named_argument_value(): void
    {
        $named = new PhpNamedArgNode($this->env(), 'out', $this->ref('m'));

        self::assertSame(['m'], $this->collect($named));
    }

    public function test_de_duplicates_repeated_locals(): void
    {
        $call = new CallNode($this->env(), $this->phpVar('f'), [$this->ref('m'), $this->ref('m')]);

        self::assertSame(['m'], $this->collect($call));
    }

    public function test_collects_php_ref_from_a_set_literal(): void
    {
        $set = new SetNode($this->env(), [$this->ref('m')]);

        self::assertSame(['m'], $this->collect($set));
    }

    public function test_stops_at_a_closure_boundary(): void
    {
        $fn = new FnNode(
            $this->env(),
            [],
            new CallNode($this->env(), $this->phpVar('f'), [$this->ref('m')]),
            [],
            false,
            false,
        );
        $do = new DoNode($this->env(), [], $fn);

        self::assertSame([], $this->collect($do));
    }

    public function test_unknown_leaf_node_collects_nothing(): void
    {
        self::assertSame([], $this->collect($this->local('m')));
    }

    /**
     * @return list<string>
     */
    private function collect(AbstractNode $node): array
    {
        return new ByRefLocalCollector()->collect($node);
    }

    private function ref(string $name): PhpRefNode
    {
        return new PhpRefNode($this->env(), $this->local($name));
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
