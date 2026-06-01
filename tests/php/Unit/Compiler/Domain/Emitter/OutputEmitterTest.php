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
}
