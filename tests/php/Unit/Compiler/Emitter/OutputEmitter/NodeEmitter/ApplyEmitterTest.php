<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Analyzer\Ast\ApplyNode;
use Phel\Compiler\Analyzer\Ast\FnNode;
use Phel\Compiler\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Analyzer\Ast\TupleNode;
use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitter\ApplyEmitter;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class ApplyEmitterTest extends TestCase
{
    private ApplyEmitter $applyEmitter;

    public function setUp(): void
    {
        $outputEmitter = (new CompilerFactory())
            ->createOutputEmitter();

        $this->applyEmitter = new ApplyEmitter($outputEmitter);
    }

    public function testPhpVarNodeAndFnNodeIsInfix(): void
    {
        $node = new PhpVarNode(NodeEnvironment::empty(), '+');
        $args = [
            new TupleNode(NodeEnvironment::empty()->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION), [
                new LiteralNode(NodeEnvironment::empty()->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION), 2),
                new LiteralNode(NodeEnvironment::empty()->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION), 3),
                new LiteralNode(NodeEnvironment::empty()->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION), 4),
            ]),
        ];

        $applyNode = new ApplyNode(NodeEnvironment::empty(), $node, $args);
        $this->applyEmitter->emit($applyNode);

        $this->expectOutputString(
            'array_reduce([...((\Phel\Lang\Tuple::createBracket(2, 3, 4)) ?? [])], function($a, $b) { return ($a + $b); });'
        );
    }

    public function testPhpVarNodeButNoInfix(): void
    {
        $node = new PhpVarNode(NodeEnvironment::empty(), 'str');
        $args = [
            new LiteralNode(NodeEnvironment::empty()->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION), 'abc'),
            new TupleNode(NodeEnvironment::empty()->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION), [
                new LiteralNode(NodeEnvironment::empty()->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION), 'def'),
            ]),
        ];

        $applyNode = new ApplyNode(NodeEnvironment::empty(), $node, $args);
        $this->applyEmitter->emit($applyNode);

        $this->expectOutputString('str("abc", ...((\Phel\Lang\Tuple::createBracket("def")) ?? []));');
    }

    public function testNoPhpVarNode(): void
    {
        $fnNode = new FnNode(
            NodeEnvironment::empty(),
            [Symbol::create('x')],
            new PhpVarNode(NodeEnvironment::empty()->withContext(NodeEnvironmentInterface::CONTEXT_RETURN), 'x'),
            [],
            $isVariadic = true,
            $recurs = false
        );

        $args = [
            new TupleNode(NodeEnvironment::empty()->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION), [
                new LiteralNode(NodeEnvironment::empty()->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION), 1),
            ]),
        ];

        $applyNode = new ApplyNode(NodeEnvironment::empty(), $fnNode, $args);
        $this->applyEmitter->emit($applyNode);

        $this->expectOutputString('(new class() extends \Phel\Lang\AbstractFn {
  public const BOUND_TO = "";

  public function __invoke(...$x) {
    $x = new \Phel\Lang\PhelArray($x);
    return x;
  }
};)(...((\Phel\Lang\Tuple::createBracket(1)) ?? []));');
    }
}
