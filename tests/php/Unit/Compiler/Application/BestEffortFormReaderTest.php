<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Application;

use Gacela\Framework\Attribute\CacheableConfig;
use Gacela\Framework\Gacela;
use Phel\Compiler\CompilerFacade;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

/**
 * `readFormsBestEffort` is what the linter, the project indexer and
 * completion-at-point run over buffers the user is still editing, so the
 * contract that matters is what survives a broken form, not what fails.
 */
final class BestEffortFormReaderTest extends TestCase
{
    private CompilerFacade $compilerFacade;

    protected function setUp(): void
    {
        CacheableConfig::reset();
        Gacela::bootstrap(__DIR__);
        $this->compilerFacade = new CompilerFacade();
    }

    protected function tearDown(): void
    {
        CacheableConfig::reset();
    }

    public function test_it_yields_every_top_level_form(): void
    {
        $forms = $this->read('(def a 1) (def b 2) (def c 3)');

        self::assertCount(3, $forms);
        self::assertSame(['a', 'b', 'c'], array_map($this->secondSymbolName(...), $forms));
    }

    public function test_it_skips_comments_and_whitespace(): void
    {
        $forms = $this->read(";; a comment\n\n(def a 1)\n\n;; another\n");

        self::assertCount(1, $forms);
    }

    public function test_it_yields_nothing_for_an_empty_source(): void
    {
        self::assertSame([], $this->read(''));
    }

    public function test_it_keeps_the_forms_read_before_an_unfinished_form(): void
    {
        $forms = $this->read('(def a 1) (def b 2) (def c');

        self::assertCount(2, $forms);
        self::assertSame(['a', 'b'], array_map($this->secondSymbolName(...), $forms));
    }

    public function test_it_keeps_the_forms_read_before_a_lexer_failure(): void
    {
        // An unterminated `#| |#` block comment; the lexer is itself a
        // generator, so this only blows up mid-iteration.
        $forms = $this->read('(def a 1) #| never closed');

        self::assertCount(1, $forms);
    }

    public function test_it_is_lazy_so_a_broken_tail_costs_nothing_when_unread(): void
    {
        $generator = $this->compilerFacade->readFormsBestEffort('(def a 1) (def b', 'lazy.phel');

        self::assertTrue($generator->valid());
        self::assertSame('a', $this->secondSymbolName($generator->current()));
    }

    /**
     * @return list<mixed>
     */
    private function read(string $code): array
    {
        return iterator_to_array($this->compilerFacade->readFormsBestEffort($code, 'best-effort.phel'), false);
    }

    private function secondSymbolName(mixed $form): string
    {
        self::assertInstanceOf(PersistentListInterface::class, $form);
        $name = $form->get(1);
        self::assertInstanceOf(Symbol::class, $name);

        return $name->getName();
    }
}
