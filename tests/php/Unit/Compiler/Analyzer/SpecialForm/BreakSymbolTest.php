<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\BreakSymbol;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\AbstractLocatedException;
use PHPUnit\Framework\TestCase;

use function ob_get_clean;
use function ob_start;
use function sprintf;

final class BreakSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function test_wrong_symbol_name(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("This is not a 'break.");

        $list = Phel::list([Symbol::create('unknown')]);
        new BreakSymbol($this->analyzer)->analyze($list, NodeEnvironment::empty());
    }

    public function test_takes_no_arguments(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("'break takes no arguments");

        $list = Phel::list([
            Symbol::create(Symbol::NAME_BREAK),
            Symbol::create('x'),
        ]);

        new BreakSymbol($this->analyzer)->analyze($list, NodeEnvironment::empty());
    }

    public function test_break_with_no_locals(): void
    {
        $node = new BreakSymbol($this->analyzer)->analyze(
            Phel::list([Symbol::create(Symbol::NAME_BREAK)]),
            NodeEnvironment::empty(),
        );

        self::assertSame($this->expectedBreakpointCall(''), $this->emit($node));
    }

    public function test_break_captures_lexical_locals(): void
    {
        $env = new NodeEnvironment(
            [Symbol::create('x'), Symbol::create('y')],
            NodeEnvironment::CONTEXT_STATEMENT,
            [],
            [],
        );

        $node = new BreakSymbol($this->analyzer)->analyze(
            Phel::list([Symbol::create(Symbol::NAME_BREAK)]),
            $env,
        );

        self::assertSame(
            $this->expectedBreakpointCall("\n  \"x\", \$x,\n  \"y\", \$y\n"),
            $this->emit($node),
        );
    }

    public function test_break_skips_internal_gensym_locals(): void
    {
        $env = new NodeEnvironment(
            [Symbol::create('x'), Symbol::create('__phel_1')],
            NodeEnvironment::CONTEXT_STATEMENT,
            [],
            [],
        );

        $node = new BreakSymbol($this->analyzer)->analyze(
            Phel::list([Symbol::create(Symbol::NAME_BREAK)]),
            $env,
        );

        self::assertSame(
            $this->expectedBreakpointCall("\n  \"x\", \$x\n"),
            $this->emit($node),
        );
    }

    public function test_break_dedupes_locals_by_name(): void
    {
        $env = new NodeEnvironment(
            [Symbol::create('x'), Symbol::create('x')],
            NodeEnvironment::CONTEXT_STATEMENT,
            [],
            [],
        );

        $node = new BreakSymbol($this->analyzer)->analyze(
            Phel::list([Symbol::create(Symbol::NAME_BREAK)]),
            $env,
        );

        self::assertSame(
            $this->expectedBreakpointCall("\n  \"x\", \$x\n"),
            $this->emit($node),
        );
    }

    private function expectedBreakpointCall(string $mapArgs): string
    {
        return sprintf('(\%s::breakpoint(\%s::map(%s)));', Phel::class, Phel::class, $mapArgs);
    }

    private function emit(AbstractNode $node): string
    {
        $outputEmitter = new CompilerFactory()->createOutputEmitter();

        ob_start();
        $outputEmitter->emitNode($node);

        return ob_get_clean();
    }
}
