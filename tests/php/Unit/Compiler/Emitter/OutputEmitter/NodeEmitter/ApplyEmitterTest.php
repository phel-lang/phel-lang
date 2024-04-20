<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Lang\Symbol;
use Phel\Transpiler\Domain\Analyzer\Ast\ApplyNode;
use Phel\Transpiler\Domain\Analyzer\Ast\FnNode;
use Phel\Transpiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Transpiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter\ApplyEmitter;
use Phel\Transpiler\TranspilerFactory;
use PHPUnit\Framework\TestCase;

final class ApplyEmitterTest extends TestCase
{
    private ApplyEmitter $applyEmitter;

    protected function setUp(): void
    {
        $outputEmitter = (new TranspilerFactory())
            ->createOutputEmitter();

        $this->applyEmitter = new ApplyEmitter($outputEmitter);
    }

    public function test_php_var_node_and_fn_node_is_infix(): void
    {
        $node = new PhpVarNode(NodeEnvironment::empty(), '+');
        $args = [
            new VectorNode(NodeEnvironment::empty()->withExpressionContext(), [
                new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 2),
                new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 3),
                new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 4),
            ]),
        ];

        $applyNode = new ApplyNode(NodeEnvironment::empty(), $node, $args);
        $this->applyEmitter->emit($applyNode);

        $this->expectOutputString(
            'array_reduce([...((\Phel\Lang\TypeFactory::getInstance()->persistentVectorFromArray([2, 3, 4])) ?? [])], function($a, $b) { return ($a + $b); });',
        );
    }

    public function test_php_var_node_but_no_infix(): void
    {
        $node = new PhpVarNode(NodeEnvironment::empty(), 'str');
        $args = [
            new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 'abc'),
            new VectorNode(NodeEnvironment::empty()->withExpressionContext(), [
                new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 'def'),
            ]),
        ];

        $applyNode = new ApplyNode(NodeEnvironment::empty(), $node, $args);
        $this->applyEmitter->emit($applyNode);

        $this->expectOutputString('str("abc", ...((\Phel\Lang\TypeFactory::getInstance()->persistentVectorFromArray(["def"])) ?? []));');
    }

    public function test_no_php_var_node(): void
    {
        $fnNode = new FnNode(
            NodeEnvironment::empty(),
            [Symbol::create('x')],
            new PhpVarNode(NodeEnvironment::empty()->withReturnContext(), 'x'),
            [],
            isVariadic: true,
            recurs: false,
        );

        $args = [
            new VectorNode(NodeEnvironment::empty()->withExpressionContext(), [
                new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 1),
            ]),
        ];

        $applyNode = new ApplyNode(NodeEnvironment::empty(), $fnNode, $args);
        $this->applyEmitter->emit($applyNode);

        $this->expectOutputString('(new class() extends \Phel\Lang\AbstractFn {
  public const BOUND_TO = "";

  public function __invoke(...$x) {
    $x = \Phel\Lang\TypeFactory::getInstance()->persistentVectorFromArray($x);
    return x;
  }
};)(...((\Phel\Lang\TypeFactory::getInstance()->persistentVectorFromArray([1])) ?? []));');
    }
}
