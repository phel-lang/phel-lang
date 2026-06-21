<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter;

use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use PHPUnit\Framework\TestCase;

final class OutputEmitterTest extends TestCase
{
    private OutputEmitterInterface $outputEmitter;

    protected function setUp(): void
    {
        $this->outputEmitter = new CompilerFactory()->createOutputEmitter();
    }

    public function test_capture_strips_return_prefix_from_return_context_node(): void
    {
        // A literal in RETURN context emits `return 42;`.
        $node = new LiteralNode(NodeEnvironment::empty()->withReturnContext(), 42);

        self::assertSame('42', $this->outputEmitter->captureNodeAsExpression($node));
    }

    public function test_capture_returns_empty_for_dropped_statement_literal(): void
    {
        // A bare literal is a pure value, so in STATEMENT context it is
        // dropped entirely and nothing is emitted.
        $node = new LiteralNode(NodeEnvironment::empty(), 42);

        self::assertSame('', $this->outputEmitter->captureNodeAsExpression($node));
    }

    public function test_capture_leaves_expression_context_node_untouched(): void
    {
        // A literal in EXPRESSION context already emits a bare `42`.
        $node = new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 42);

        self::assertSame('42', $this->outputEmitter->captureNodeAsExpression($node));
    }

    public function test_capture_does_not_strip_return_inside_string_literal(): void
    {
        // A string literal whose value starts with the word `return` must
        // keep that text: only the emitted `return ` statement prefix is
        // stripped, never content embedded in the value. Guards the
        // `str_starts_with` + `substr` replacement against over-stripping.
        $node = new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 'return x');

        self::assertSame('"return x"', $this->outputEmitter->captureNodeAsExpression($node));
    }

    public function test_capture_strips_return_prefix_for_string_literal_in_return_context(): void
    {
        // In RETURN context the same string literal emits
        // `return "return x";`; only the leading statement prefix and the
        // trailing `;` are stripped, leaving the bare expression intact.
        $node = new LiteralNode(NodeEnvironment::empty()->withReturnContext(), 'return x');

        self::assertSame('"return x"', $this->outputEmitter->captureNodeAsExpression($node));
    }
}
