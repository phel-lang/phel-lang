<?php

declare(strict_types=1);

namespace PhelTest\Emitter\OutputEmitter\NodeEmitter;

use Phel\Ast\ApplyNode;
use Phel\Ast\FnNode;
use Phel\Ast\LiteralNode;
use Phel\Ast\PhpVarNode;
use Phel\Ast\TupleNode;
use Phel\Emitter\OutputEmitter;
use Phel\Emitter\OutputEmitter\NodeEmitter\ApplyEmitter;
use Phel\Lang\Symbol;
use Phel\NodeEnvironment;
use PHPUnit\Framework\TestCase;

final class ApplyEmitterTest extends TestCase
{
    private ApplyEmitter $applyEmitter;

    public function setUp(): void
    {
        $this->applyEmitter = new ApplyEmitter(
            OutputEmitter::createWithSourceMap()
        );
    }

    public function testPhpVarNodeAndFnNodeIsInfix(): void
    {
        $node = new PhpVarNode(NodeEnvironment::empty(), '+');
        $args = [
            new TupleNode(NodeEnvironment::empty(), [
                new LiteralNode(NodeEnvironment::empty(), 2),
                new LiteralNode(NodeEnvironment::empty(), 3),
                new LiteralNode(NodeEnvironment::empty(), 4),
            ]),
        ];

        $applyNode = new ApplyNode(NodeEnvironment::empty(), $node, $args);
        $this->applyEmitter->emit($applyNode);

        $this->expectOutputString(
            'array_reduce([...((\Phel\Lang\Tuple::createBracket(, , );) ?? [])], function($a, $b) { return ($a + $b); });'
        );
    }

    public function testPhpVarNodeButNoInfix(): void
    {
        $node = new PhpVarNode(NodeEnvironment::empty(), 'str');
        $args = [
            new LiteralNode(NodeEnvironment::empty(), 'abc'),
            new TupleNode(NodeEnvironment::empty(), [
                new LiteralNode(NodeEnvironment::empty(), 'def'),
            ]),
        ];

        $applyNode = new ApplyNode(NodeEnvironment::empty(), $node, $args);
        $this->applyEmitter->emit($applyNode);

        $this->expectOutputString('str(, ...((\Phel\Lang\Tuple::createBracket();) ?? []));');
    }

    public function testNoPhpVarNode(): void
    {
        $fnNode = new FnNode(
            NodeEnvironment::empty(),
            [Symbol::create('x')],
            new PhpVarNode(NodeEnvironment::empty(), 'x'),
            [],
        $isVariadic = true,
        $recurs = false
        );

        $args = [
            new TupleNode(NodeEnvironment::empty(), [
                new LiteralNode(NodeEnvironment::empty(), 1),
            ]),
        ];

        $applyNode = new ApplyNode(NodeEnvironment::empty(), $fnNode, $args);
        $this->applyEmitter->emit($applyNode);

        $this->expectOutputString('(new class() extends \Phel\Lang\AFn {
  public const BOUND_TO = "";

  public function __invoke(...$x) {
    $x = new \Phel\Lang\PhelArray($x);
    x;
  }
};)(...((\Phel\Lang\Tuple::createBracket();) ?? []));');
    }
}
