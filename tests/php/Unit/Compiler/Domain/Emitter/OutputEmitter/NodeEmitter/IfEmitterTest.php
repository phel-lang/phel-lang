<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\IfSymbol;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\IfEmitter;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class IfEmitterTest extends TestCase
{
    private IfEmitter $ifEmitter;

    private Analyzer $analyzer;

    protected function setUp(): void
    {
        $this->ifEmitter = new IfEmitter(new CompilerFactory()->createOutputEmitter());
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function test_statement_context_emits_if_else_block(): void
    {
        $output = $this->emit(NodeEnvironment::empty());

        // A bare string literal is a pure value, so in statement context both
        // branch bodies are dropped — only the if/else structure is emitted.
        self::assertStringContainsString('if (', $output);
        self::assertStringContainsString('} else {', $output);
    }

    public function test_expression_context_emits_ternary(): void
    {
        $output = $this->emit(NodeEnvironment::empty()->withExpressionContext());

        self::assertStringStartsWith('(', $output);
        self::assertStringContainsString(' ? ', $output);
        self::assertStringContainsString(' : ', $output);
        self::assertStringNotContainsString('} else {', $output);
        // As ternary operands both branch values are emitted.
        self::assertStringContainsString('then-branch', $output);
        self::assertStringContainsString('else-branch', $output);
    }

    public function test_return_context_collapses_to_return_ternary(): void
    {
        $output = $this->emit(NodeEnvironment::empty()->withReturnContext());

        self::assertStringContainsString('return (', $output);
        self::assertStringContainsString(' ? ', $output);
        self::assertStringNotContainsString('} else {', $output);
        self::assertStringContainsString('then-branch', $output);
        self::assertStringContainsString('else-branch', $output);
    }

    public function test_statement_context_with_nil_else_drops_else_branch(): void
    {
        $output = $this->emit(NodeEnvironment::empty(), withElse: false);

        self::assertStringContainsString('if (', $output);
        self::assertStringNotContainsString('else', $output);
    }

    private function emit(NodeEnvironmentInterface $env, bool $withElse = true): string
    {
        // Wrap the test in `(do true)` so IfSymbol does not constant-fold the
        // literal test away and we keep a real IfNode to emit.
        $form = [
            Symbol::create(Symbol::NAME_IF),
            Phel::list([Symbol::create(Symbol::NAME_DO), true]),
            'then-branch',
        ];
        if ($withElse) {
            $form[] = 'else-branch';
        }

        $node = new IfSymbol($this->analyzer)->analyze(Phel::list($form), $env);
        self::assertInstanceOf(IfNode::class, $node);

        ob_start();
        $this->ifEmitter->emit($node);

        return (string) ob_get_clean();
    }
}
