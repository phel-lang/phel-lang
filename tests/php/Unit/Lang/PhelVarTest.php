<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Phel;
use Phel\Lang\PhelVar;
use Phel\Lang\Registry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PhelVarTest extends TestCase
{
    private Registry $registry;

    public static function tearDownAfterClass(): void
    {
        Registry::getInstance()->clear();
    }

    protected function setUp(): void
    {
        $this->registry = Registry::getInstance();
        $this->registry->clear();
    }

    public function test_get_full_name(): void
    {
        $var = new PhelVar('user', 'x');

        self::assertSame('user', $var->getNamespace());
        self::assertSame('x', $var->getName());
        self::assertSame('user/x', $var->getFullName());
    }

    public function test_deref_returns_current_root_value(): void
    {
        $var = $this->registry->addDefinition('user', 'x', 42);

        self::assertSame(42, $var->deref());
    }

    public function test_deref_reflects_subsequent_root_change(): void
    {
        $var = $this->registry->addDefinition('user', 'x', 1);
        $this->registry->addDefinition('user', 'x', 2);

        self::assertSame(2, $var->deref());
    }

    public function test_deref_throws_when_definition_removed(): void
    {
        $var = $this->registry->addDefinition('user', 'x', 1);
        $this->registry->removeNamespace('user');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cannot deref var #'user/x");
        $var->deref();
    }

    public function test_meta_returns_attached_metadata(): void
    {
        $meta = Phel::map(Phel::keyword('doc'), 'docstring');
        $var = $this->registry->addDefinition('user', 'x', 1, $meta);

        self::assertSame($meta, $var->meta());
    }

    public function test_meta_returns_empty_map_for_definition_without_metadata(): void
    {
        $var = $this->registry->addDefinition('user', 'x', 1);

        self::assertNotNull($var->meta());
    }

    public function test_alter_root_replaces_value_and_returns_new(): void
    {
        $var = $this->registry->addDefinition('user', 'counter', 1);

        $next = $var->alterRoot(static fn(int $n): int => $n + 1);

        self::assertSame(2, $next);
        self::assertSame(2, $this->registry->getDefinition('user', 'counter'));
    }

    public function test_alter_root_passes_extra_arguments(): void
    {
        $var = $this->registry->addDefinition('user', 'counter', 1);

        $next = $var->alterRoot(static fn(int $n, int $a, int $b): int => $n + $a + $b, 10, 100);

        self::assertSame(111, $next);
    }

    public function test_alter_root_preserves_metadata(): void
    {
        $meta = Phel::map(Phel::keyword('doc'), 'docstring');
        $var = $this->registry->addDefinition('user', 'x', 1, $meta);

        $var->alterRoot(static fn(int $n): int => $n + 1);

        self::assertSame($meta, $this->registry->getDefinitionMetaData('user', 'x'));
    }

    public function test_alter_root_throws_when_definition_removed(): void
    {
        $var = $this->registry->addDefinition('user', 'x', 1);
        $this->registry->removeNamespace('user');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cannot alter-var-root on #'user/x");
        $var->alterRoot(static fn(int $n): int => $n + 1);
    }

    public function test_two_vars_to_same_slot_compare_equal(): void
    {
        $a = new PhelVar('user', 'x');
        $b = new PhelVar('user', 'x');

        self::assertTrue($a->equals($b));
        self::assertSame($a->hash(), $b->hash());
    }

    public function test_vars_with_different_names_not_equal(): void
    {
        $a = new PhelVar('user', 'x');
        $b = new PhelVar('user', 'y');

        self::assertFalse($a->equals($b));
    }

    public function test_vars_with_different_namespaces_not_equal(): void
    {
        $a = new PhelVar('user', 'x');
        $b = new PhelVar('app', 'x');

        self::assertFalse($a->equals($b));
    }

    public function test_var_does_not_equal_non_var(): void
    {
        $a = new PhelVar('user', 'x');

        self::assertFalse($a->equals('user/x'));
        self::assertFalse($a->equals(null));
    }
}
