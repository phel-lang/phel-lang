<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\BindingValidator;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\LetSymbol;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\LetEmitter;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class LetEmitterTest extends TestCase
{
    private LetEmitter $letEmitter;

    private LetSymbol $letSymbol;

    protected function setUp(): void
    {
        $this->letEmitter = new LetEmitter(new CompilerFactory()->createOutputEmitter());
        $this->letSymbol = new LetSymbol(
            new Analyzer(new GlobalEnvironment()),
            new Deconstructor(new BindingValidator()),
        );
    }

    public function test_statement_context_emits_plain_binding_assignments(): void
    {
        $output = $this->emit(NodeEnvironment::empty());

        self::assertStringContainsString(' = ', $output);
        self::assertStringContainsString(';', $output);
        self::assertStringNotContainsString('(function()', $output);
    }

    public function test_expression_context_wraps_in_an_iife(): void
    {
        $output = $this->emit(NodeEnvironment::empty()->withExpressionContext());

        self::assertStringContainsString('(function()', $output);
        self::assertStringContainsString('})()', $output);
        self::assertStringContainsString(' = ', $output);
    }

    public function test_return_context_emits_bindings_without_iife_and_returns_body(): void
    {
        $output = $this->emit(NodeEnvironment::empty()->withReturnContext());

        self::assertStringNotContainsString('(function()', $output);
        self::assertStringContainsString(' = ', $output);
        self::assertStringContainsString('return ', $output);
    }

    private function emit(NodeEnvironmentInterface $env): string
    {
        // `x` is referenced twice in the body, so LetSimplifier neither drops
        // the binding (it stays referenced) nor inlines it (the body tail is a
        // call, not a bare `x` local) — we keep a real LetNode to emit.
        $list = Phel::list([
            Symbol::create(Symbol::NAME_LET),
            Phel::vector([
                Symbol::create('x'),
                Phel::list([Symbol::create('php/+'), 1, 1]),
            ]),
            Phel::list([Symbol::create('php/+'), Symbol::create('x'), Symbol::create('x')]),
        ]);

        $node = $this->letSymbol->analyze($list, $env);
        self::assertInstanceOf(LetNode::class, $node);

        ob_start();
        $this->letEmitter->emit($node);

        return (string) ob_get_clean();
    }
}
