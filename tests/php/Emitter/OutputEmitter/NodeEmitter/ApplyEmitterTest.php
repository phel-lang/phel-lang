<?php

declare(strict_types=1);

namespace PhelTest\Emitter\OutputEmitter\NodeEmitter;

use Phel\Ast\ApplyNode;
use Phel\Ast\DefNode;
use Phel\Ast\LiteralNode;
use Phel\Ast\PhpVarNode;
use Phel\Ast\TupleNode;
use Phel\Emitter\OutputEmitter;
use Phel\Emitter\OutputEmitter\Munge;
use Phel\Emitter\OutputEmitter\NodeEmitter\ApplyEmitter;
use Phel\Emitter\OutputEmitter\NodeEmitterFactory;
use Phel\Emitter\OutputEmitter\SourceMap\SourceMapGenerator;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\NodeEnvironment;
use PHPUnit\Framework\TestCase;

final class ApplyEmitterTest extends TestCase
{
    private ApplyEmitter $applyEmitter;

    public function setUp(): void
    {
        $this->applyEmitter = new ApplyEmitter(
            new OutputEmitter(
                $enableSourceMaps = true,
                new SourceMapGenerator(),
                new NodeEmitterFactory(),
                new Munge()
            )
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

        $arrayNode = new ApplyNode(NodeEnvironment::empty(), $node, $args);
        $this->applyEmitter->emit($arrayNode);

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
                new LiteralNode(NodeEnvironment::empty(), 'def')
            ]),
        ];

        $arrayNode = new ApplyNode(NodeEnvironment::empty(), $node, $args);
        $this->applyEmitter->emit($arrayNode);

        $this->expectOutputString('str(, ...((\Phel\Lang\Tuple::createBracket();) ?? []));');
    }

    public function testNoPhpVarNode(): void
    {
        $defNode = new DefNode(
            NodeEnvironment::empty(),
            'user',
            Symbol::create('foo'),
            Table::empty(),
            new PhpVarNode(NodeEnvironment::empty(), 'println')
        );

        $args = [
            new TupleNode(NodeEnvironment::empty(), [
                new LiteralNode(NodeEnvironment::empty(), true),
            ]),
        ];

        $arrayNode = new ApplyNode(NodeEnvironment::empty(), $defNode, $args);
        $this->applyEmitter->emit($arrayNode);

        $this->expectOutputString('($GLOBALS["__phel"]["user"]["foo"] = println;;
)(...((\Phel\Lang\Tuple::createBracket();) ?? []));');
    }
}
