<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Closure;
use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\ErrorCode;
use PHPUnit\Framework\TestCase;

final class MacroExpansionDepthTest extends TestCase
{
    private Analyzer $analyzer;

    private GlobalEnvironment $environment;

    protected function setUp(): void
    {
        Phel::clear();
        $this->environment = new GlobalEnvironment();
        $this->analyzer = new Analyzer($this->environment);
    }

    public function test_self_expansion_unwinds_after_repeated_failures(): void
    {
        $this->macro('recursive', static fn($form) => $form);
        $this->macro('finite', static fn($form, $env, $n): PersistentListInterface|int => $n === 0 ? 42 : Phel::list([Symbol::create('finite'), $n - 1]));
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $this->assertExpansionFails('recursive');
            self::assertFalse($this->environment->isPrivateAccessAllowed());
            $result = $this->analyzer->analyze(Phel::list([Symbol::create('finite'), 500]), NodeEnvironment::empty());
            self::assertInstanceOf(LiteralNode::class, $result);
            self::assertSame(42, $result->getValue());
        }
    }

    public function test_depth_error_keeps_the_call_location_when_expansion_returns_a_scalar(): void
    {
        $remaining = 1000;
        $this->macro('finite', static function () use (&$remaining): PersistentListInterface|int {
            return $remaining-- === 0 ? 42 : Phel::list([Symbol::create('finite')]);
        });
        $this->assertExpansionFails('finite');
        self::assertFalse($this->environment->isPrivateAccessAllowed());
    }

    public function test_mutually_recursive_macros_share_the_depth_limit(): void
    {
        $this->macro('first', static fn(): PersistentListInterface => Phel::list([Symbol::create('second')]));
        $this->macro('second', static fn(): PersistentListInterface => Phel::list([Symbol::create('first')]));
        $this->assertExpansionFails('first');
        self::assertFalse($this->environment->isPrivateAccessAllowed());
    }

    public function test_inline_and_global_expansions_share_the_depth_limit(): void
    {
        $this->macro('global', static fn(): PersistentListInterface => Phel::list([Symbol::create('inline')]));
        $this->macro('inline', static fn(): PersistentListInterface => Phel::list([Symbol::create('global')]), inline: true);
        $this->assertExpansionFails('global');
        self::assertFalse($this->environment->isPrivateAccessAllowed());
    }

    public function test_separate_expansions_do_not_accumulate_depth(): void
    {
        $this->macro('finite', static fn(): int => 42);
        for ($index = 0; $index < 1100; ++$index) {
            $result = $this->analyzer->analyze(Phel::list([Symbol::create('finite')]), NodeEnvironment::empty());
            self::assertInstanceOf(LiteralNode::class, $result);
            self::assertSame(42, $result->getValue());
        }

        self::assertFalse($this->environment->isPrivateAccessAllowed());
    }

    private function macro(string $name, Closure $fn, bool $inline = false): void
    {
        $this->environment->addDefinition('user', Symbol::create($name));
        $meta = $inline ? Phel::map(Keyword::create('inline'), $fn) : Phel::map(Keyword::create('macro'), true);
        Phel::addDefinition('user', $name, $fn, $meta);
    }

    private function assertExpansionFails(string $name): void
    {
        $form = Phel::list([Symbol::create($name)])->setStartLocation(new SourceLocation('macro.phel', 4, 0));
        try {
            $this->analyzer->analyze($form, NodeEnvironment::empty());
            self::fail('Expected a macro expansion depth error');
        } catch (AnalyzerException $analyzerException) {
            self::assertSame(ErrorCode::MACRO_EXPANSION_ERROR, $analyzerException->getErrorCode());
            self::assertStringContainsString('expansion depth', $analyzerException->getMessage());
            self::assertSame(4, $analyzerException->getStartLocation()->getLine());
        }
    }
}
