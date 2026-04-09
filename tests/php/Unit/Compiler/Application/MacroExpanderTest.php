<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Application;

use Phel;
use Phel\Compiler\Application\MacroExpander;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use PHPUnit\Framework\TestCase;

final class MacroExpanderTest extends TestCase
{
    private MacroExpander $macroExpander;

    protected function setUp(): void
    {
        Phel::clear();
        $globalEnv = new GlobalEnvironment();

        // Register a simple macro that wraps its argument in a list.
        // Macros receive `&form` and `&env` as the first two implicit args.
        $globalEnv->addDefinition('user', Symbol::create('my-macro'));
        Phel::addDefinition(
            'user',
            'my-macro',
            static fn($form, $env, $a): PersistentListInterface => Phel::list([Symbol::create('do'), $a]),
            Phel::map(Keyword::create('macro'), true),
        );

        // Register a non-macro function
        $globalEnv->addDefinition('user', Symbol::create('my-fn'));
        Phel::addDefinition(
            'user',
            'my-fn',
            static fn($a): int|float => $a + 1,
            Phel::map(),
        );

        // Register a macro that expands to another macro call
        $globalEnv->addDefinition('user', Symbol::create('outer-macro'));
        Phel::addDefinition(
            'user',
            'outer-macro',
            static fn($form, $env, $a): PersistentListInterface => Phel::list([Symbol::createForNamespace('user', 'my-macro'), $a]),
            Phel::map(Keyword::create('macro'), true),
        );

        // Register an inline function
        $globalEnv->addDefinition('user', Symbol::create('my-inline'));
        Phel::addDefinition(
            'user',
            'my-inline',
            static fn($a) => $a,
            Phel::map(
                Keyword::create('inline'),
                static fn($a): PersistentListInterface => Phel::list([Symbol::createForNamespace('php', '+'), $a, 1]),
            ),
        );

        $this->macroExpander = new MacroExpander($globalEnv);
    }

    public function test_macroexpand1_expands_macro_once(): void
    {
        $form = Phel::list([
            Symbol::createForNamespace('user', 'my-macro'),
            42,
        ]);

        $result = $this->macroExpander->macroexpand1($form);

        self::assertInstanceOf(TypeInterface::class, $result);
        // my-macro wraps in (do 42)
        $expected = Phel::list([Symbol::create('do'), 42]);
        self::assertTrue($expected->equals($result));
    }

    public function test_macroexpand1_returns_non_macro_unchanged(): void
    {
        $form = Phel::list([
            Symbol::createForNamespace('user', 'my-fn'),
            42,
        ]);

        $result = $this->macroExpander->macroexpand1($form);

        self::assertSame($form, $result);
    }

    public function test_macroexpand1_returns_non_list_unchanged(): void
    {
        self::assertSame(42, $this->macroExpander->macroexpand1(42));
        self::assertSame('hello', $this->macroExpander->macroexpand1('hello'));
        self::assertNull($this->macroExpander->macroexpand1(null));
        self::assertTrue($this->macroExpander->macroexpand1(true));
    }

    public function test_macroexpand1_returns_empty_list_unchanged(): void
    {
        $emptyList = Phel::list([]);

        $result = $this->macroExpander->macroexpand1($emptyList);

        self::assertSame($emptyList, $result);
    }

    public function test_macroexpand1_returns_list_with_non_symbol_head_unchanged(): void
    {
        $form = Phel::list([42, 'hello']);

        $result = $this->macroExpander->macroexpand1($form);

        self::assertSame($form, $result);
    }

    public function test_macroexpand1_returns_unresolvable_symbol_unchanged(): void
    {
        $form = Phel::list([
            Symbol::create('nonexistent-fn'),
            42,
        ]);

        $result = $this->macroExpander->macroexpand1($form);

        self::assertSame($form, $result);
    }

    public function test_macroexpand1_does_not_expand_nested_macros(): void
    {
        // outer-macro expands to (my-macro 42), which is itself a macro call
        $form = Phel::list([
            Symbol::createForNamespace('user', 'outer-macro'),
            42,
        ]);

        $result = $this->macroExpander->macroexpand1($form);

        // Should only expand once: (my-macro 42), not (do 42)
        $expected = Phel::list([Symbol::createForNamespace('user', 'my-macro'), 42]);
        self::assertTrue($expected->equals($result));
    }

    public function test_macroexpand_fully_expands_nested_macros(): void
    {
        // outer-macro -> (my-macro 42) -> (do 42)
        $form = Phel::list([
            Symbol::createForNamespace('user', 'outer-macro'),
            42,
        ]);

        $result = $this->macroExpander->macroexpand($form);

        // Should be fully expanded: (do 42)
        $expected = Phel::list([Symbol::create('do'), 42]);
        self::assertTrue($expected->equals($result));
    }

    public function test_macroexpand_returns_non_macro_unchanged(): void
    {
        $form = Phel::list([
            Symbol::createForNamespace('user', 'my-fn'),
            42,
        ]);

        $result = $this->macroExpander->macroexpand($form);

        self::assertSame($form, $result);
    }

    public function test_macroexpand_returns_scalar_unchanged(): void
    {
        self::assertSame(42, $this->macroExpander->macroexpand(42));
        self::assertSame('hello', $this->macroExpander->macroexpand('hello'));
        self::assertNull($this->macroExpander->macroexpand(null));
    }

    public function test_macroexpand1_returns_inline_function_unchanged(): void
    {
        $form = Phel::list([
            Symbol::createForNamespace('user', 'my-inline'),
            5,
        ]);

        $result = $this->macroExpander->macroexpand1($form);

        self::assertSame($form, $result);
    }

    public function test_macroexpand1_with_symbol_form(): void
    {
        $sym = Symbol::create('foo');

        $result = $this->macroExpander->macroexpand1($sym);

        self::assertSame($sym, $result);
    }
}
