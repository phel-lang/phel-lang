<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\CallEmitter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CallEmitterTest extends TestCase
{
    private CallEmitter $callEmitter;

    protected function setUp(): void
    {
        $outputEmitter = new CompilerFactory()
            ->createOutputEmitter();

        $this->callEmitter = new CallEmitter($outputEmitter);
    }

    public function test_reference_assignment_return_uses_a_value_temporary(): void
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $call = new CallNode(
            NodeEnvironment::empty()->withReturnContext(),
            new PhpVarNode($env, '=&'),
            [new PhpVarNode($env, '$left'), new PhpVarNode($env, '$right')],
        );

        ob_start();
        $this->callEmitter->emit($call);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertMatchesRegularExpression('/^(\$reference_assignment_\d+) = \(\$left =& \$right\);\s*return \1;$/', $output);
    }

    public function test_reference_assignment_return_preserves_aliases_and_evaluates_operands_once(): void
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $call = new CallNode(
            NodeEnvironment::empty()->withReturnContext(),
            new PhpVarNode($env, '=&'),
            [new PhpVarNode($env, '$left[$i++]'), new PhpVarNode($env, '$right[$j++]')],
        );

        ob_start();
        $this->callEmitter->emit($call);
        $output = ob_get_clean();

        self::assertIsString($output);
        $bind = eval('return function (&$left, &$right, &$i, &$j) {' . $output . '};');
        $left = [0];
        $right = [7];
        $i = 0;
        $j = 0;
        $result = $bind($left, $right, $i, $j);
        $right[0] = 8;

        self::assertSame([7, 8, 1, 1], [$result, $left[0], $i, $j]);
    }

    #[DataProvider('reference_assignment_non_return_contexts')]
    public function test_reference_assignment_non_return_emission_is_unchanged(bool $isExpression, string $expected): void
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $call = new CallNode(
            $isExpression ? $env : NodeEnvironment::empty(),
            new PhpVarNode($env, '=&'),
            [new PhpVarNode($env, '$left'), new PhpVarNode($env, '$right')],
        );

        $this->callEmitter->emit($call);

        $this->expectOutputString($expected);
    }

    public static function reference_assignment_non_return_contexts(): iterable
    {
        yield 'expression' => [true, '($left =& $right)'];
        yield 'statement' => [false, '($left =& $right);'];
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

    public function test_yield_in_return_context_omits_return_keyword(): void
    {
        $node = new PhpVarNode(NodeEnvironment::empty()->withReturnContext(), 'yield');
        $args = [
            new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 'abc'),
        ];

        $applyNode = new CallNode(NodeEnvironment::empty()->withReturnContext(), $node, $args);
        $this->callEmitter->emit($applyNode);

        $this->expectOutputString('yield "abc";');
    }

    public function test_yield_key_value_in_return_context_omits_return_keyword(): void
    {
        $node = new PhpVarNode(NodeEnvironment::empty()->withReturnContext(), 'yield');
        $args = [
            new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 1),
            new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 2),
        ];

        $applyNode = new CallNode(NodeEnvironment::empty()->withReturnContext(), $node, $args);
        $this->callEmitter->emit($applyNode);

        $this->expectOutputString('yield 1 => 2;');
    }

    public function test_non_yield_in_return_context_emits_return(): void
    {
        $node = new PhpVarNode(NodeEnvironment::empty()->withReturnContext(), 'print');
        $args = [
            new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 'abc'),
        ];

        $applyNode = new CallNode(NodeEnvironment::empty()->withReturnContext(), $node, $args);
        $this->callEmitter->emit($applyNode);

        $this->expectOutputString('return print("abc");');
    }

    #[DataProvider('providerNamespacedPhpFunctionForms')]
    public function test_namespaced_php_function_is_emitted_as_fully_qualified(string $name): void
    {
        $node = new PhpVarNode(NodeEnvironment::empty(), $name);
        $args = [
            new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 'foo.txt'),
            new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 'data'),
        ];

        $applyNode = new CallNode(NodeEnvironment::empty(), $node, $args);
        $this->callEmitter->emit($applyNode);

        $this->expectOutputString('\Amp\File\write("foo.txt", "data");');
    }

    public static function providerNamespacedPhpFunctionForms(): iterable
    {
        yield 'backslash separators' => ['Amp\\File\\write'];
        yield 'dotted namespace, slash before fn' => ['Amp.File/write'];
        yield 'fully dotted' => ['Amp.File.write'];
    }
}
