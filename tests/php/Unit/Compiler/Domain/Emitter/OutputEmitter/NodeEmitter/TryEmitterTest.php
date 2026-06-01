<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Domain\Analyzer\Ast\TryNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\TrySymbol;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\TryEmitter;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class TryEmitterTest extends TestCase
{
    private TryEmitter $tryEmitter;

    private TrySymbol $trySymbol;

    protected function setUp(): void
    {
        $this->tryEmitter = new TryEmitter(new CompilerFactory()->createOutputEmitter());
        $this->trySymbol = new TrySymbol(new Analyzer(new GlobalEnvironment()));
    }

    public function test_statement_context_emits_try_catch(): void
    {
        $output = $this->emit(NodeEnvironment::empty(), withCatch: true);

        self::assertStringContainsString('try {', $output);
        self::assertStringContainsString('} catch (', $output);
        self::assertStringNotContainsString('(function()', $output);
    }

    public function test_return_context_emits_try_catch_without_iife(): void
    {
        $output = $this->emit(NodeEnvironment::empty()->withReturnContext(), withCatch: true);

        self::assertStringNotContainsString('(function()', $output);
        self::assertStringContainsString('try {', $output);
        self::assertStringContainsString('} catch (', $output);
    }

    public function test_expression_context_wraps_try_catch_in_an_iife(): void
    {
        $output = $this->emit(NodeEnvironment::empty()->withExpressionContext(), withCatch: true);

        self::assertStringContainsString('(function()', $output);
        self::assertStringContainsString('})()', $output);
        self::assertStringContainsString('try {', $output);
    }

    public function test_body_only_try_emits_body_without_try_block(): void
    {
        $output = $this->emit(NodeEnvironment::empty(), withCatch: false);

        self::assertStringNotContainsString('try {', $output);
        self::assertStringContainsString('1 + 1', $output);
    }

    private function emit(NodeEnvironmentInterface $env, bool $withCatch): string
    {
        $form = [
            Symbol::create(Symbol::NAME_TRY),
            Phel::list([Symbol::create('php/+'), 1, 1]),
        ];
        if ($withCatch) {
            $form[] = Phel::list([
                Symbol::create(Symbol::NAME_CATCH),
                Symbol::create('\\Exception'),
                Symbol::create('e'),
                Phel::list([Symbol::create('php/+'), 2, 2]),
            ]);
        }

        $node = $this->trySymbol->analyze(Phel::list($form), $env);
        self::assertInstanceOf(TryNode::class, $node);

        ob_start();
        $this->tryEmitter->emit($node);

        return (string) ob_get_clean();
    }
}
