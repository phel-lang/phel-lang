<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\CallEmitter;
use PHPUnit\Framework\TestCase;

final class CallEmitterTest extends TestCase
{
    private CallEmitter $callEmitter;

    protected function setUp(): void
    {
        $outputEmitter = (new CompilerFactory())
            ->createOutputEmitter();

        $this->callEmitter = new CallEmitter($outputEmitter);
    }

    public function test_php_var_node_print_language_constructs(): void
    {
        $node = new PhpVarNode(NodeEnvironment::empty(), 'print');
        $args = [
            new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 'abc'),
        ];

        $applyNode = new CallNode(NodeEnvironment::empty(), $node, $args);
        $this->callEmitter->emit($applyNode);

        $this->expectOutputString('print("abc");');
    }

    public function test_php_var_node_print_language_constructs_multi_auguments(): void
    {
        $node = new PhpVarNode(NodeEnvironment::empty(), 'print');
        $args = [
            new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 'abc'),
            new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 'def'),
        ];

        $applyNode = new CallNode(NodeEnvironment::empty(), $node, $args);
        $this->callEmitter->emit($applyNode);

        $this->expectOutputString('print("abc", "def");');
    }

    public function test_php_var_node_echo_language_constructs(): void
    {
        $node = new PhpVarNode(NodeEnvironment::empty(), 'echo');
        $args = [
            new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 'abc'),
        ];

        $applyNode = new CallNode(NodeEnvironment::empty(), $node, $args);
        $this->callEmitter->emit($applyNode);

        $this->expectOutputString('print("abc");');
    }

    public function test_php_var_node_yield_language_construct(): void
    {
        $node = new PhpVarNode(NodeEnvironment::empty(), 'yield');
        $args = [
            new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 'abc'),
        ];

        $applyNode = new CallNode(NodeEnvironment::empty(), $node, $args);
        $this->callEmitter->emit($applyNode);

        $this->expectOutputString('yield "abc";');
    }

    public function test_php_var_node_yield_key_value(): void
    {
        $node = new PhpVarNode(NodeEnvironment::empty(), 'yield');
        $args = [
            new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 1),
            new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 2),
        ];

        $applyNode = new CallNode(NodeEnvironment::empty(), $node, $args);
        $this->callEmitter->emit($applyNode);

        $this->expectOutputString('yield 1 => 2;');
    }

}
