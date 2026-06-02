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

    /** @var array{definitions: array<string, array<string, mixed>>, definitionsMetaData: array<string, array<string, mixed>>} */
    private array $registrySnapshot;

    protected function setUp(): void
    {
        $this->registry = Registry::getInstance();
        $this->registrySnapshot = $this->registry->snapshot();
        $this->registry->clear();
    }

    protected function tearDown(): void
    {
        $this->registry->restore($this->registrySnapshot);
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

    public function test_invoke_calls_root_value(): void
    {
        $var = $this->registry->addDefinition('user', 'add', static fn(int $a, int $b): int => $a + $b);

        self::assertSame(5, $var(2, 3));
    }

    public function test_invoke_forwards_zero_args(): void
    {
        $var = $this->registry->addDefinition('user', 'pi', static fn(): float => 3.14);

        self::assertSame(3.14, $var());
    }

    public function test_invoke_throws_when_root_value_not_callable(): void
    {
        $var = $this->registry->addDefinition('user', 'data', 42);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cannot invoke #'user/data: root value is not callable (got int)");
        $var(1);
    }

    public function test_invoke_throws_when_definition_removed(): void
    {
        $var = $this->registry->addDefinition('user', 'x', static fn(): int => 1);
        $this->registry->removeNamespace('user');

        $this->expectException(RuntimeException::class);
        $var();
    }

    public function test_alter_root_notifies_watches_with_old_and_new(): void
    {
        $var = $this->registry->addDefinition('user', 'counter', 1);
        $events = [];
        $var->addWatch('log', static function (mixed $key, PhelVar $ref, mixed $old, mixed $new) use (&$events): void {
            $events[] = [$key->getName(), $ref->getFullName(), $old, $new];
        });

        $var->alterRoot(static fn(int $n): int => $n + 1);

        self::assertSame([['log', 'user/counter', 1, 2]], $events);
    }

    public function test_remove_watch_stops_notifications(): void
    {
        $var = $this->registry->addDefinition('user', 'x', 0);
        $count = 0;
        $var->addWatch('w', static function () use (&$count): void {
            ++$count;
        });
        $var->alterRoot(static fn(int $n): int => $n + 1);
        $var->removeWatch('w');
        $var->alterRoot(static fn(int $n): int => $n + 1);

        self::assertSame(1, $count);
    }

    public function test_watch_state_is_shared_across_handles(): void
    {
        $a = $this->registry->addDefinition('user', 'x', 0);
        $b = new PhelVar('user', 'x');
        $count = 0;
        $a->addWatch('w', static function () use (&$count): void {
            ++$count;
        });

        $b->alterRoot(static fn(int $n): int => $n + 1);

        self::assertSame(1, $count);
    }

    public function test_reset_meta_replaces_meta_returned_by_meta(): void
    {
        $var = $this->registry->addDefinition('user', 'x', 1, Phel::map(Phel::keyword('doc'), 'orig'));
        $next = Phel::map(Phel::keyword('doc'), 'updated');

        $var->resetMeta($next);

        self::assertSame($next, $var->meta());
    }

    public function test_alter_meta_passes_current_meta_to_fn(): void
    {
        $original = Phel::map(Phel::keyword('a'), 1);
        $var = $this->registry->addDefinition('user', 'x', 1, $original);

        $result = $var->alterMeta(static fn($current) => $current->put(Phel::keyword('b'), 2));

        self::assertSame(1, $result[Phel::keyword('a')]);
        self::assertSame(2, $result[Phel::keyword('b')]);
        self::assertSame($result, $var->meta());
    }

    public function test_meta_override_is_shared_across_handles(): void
    {
        $a = $this->registry->addDefinition('user', 'x', 1);
        $b = new PhelVar('user', 'x');
        $next = Phel::map(Phel::keyword('tag'), 'shared');

        $a->resetMeta($next);

        self::assertSame($next, $b->meta());
    }

    public function test_is_dynamic_true_when_meta_has_dynamic_keyword(): void
    {
        $meta = Phel::map(Phel::keyword('dynamic'), true);
        $var = $this->registry->addDefinition('user', '*x*', null, $meta);

        self::assertTrue($var->isDynamic());
    }

    public function test_is_dynamic_false_for_plain_var(): void
    {
        $var = $this->registry->addDefinition('user', 'x', 1);

        self::assertFalse($var->isDynamic());
    }

    public function test_is_dynamic_refreshes_after_reset_meta(): void
    {
        $var = $this->registry->addDefinition('user', 'x', 1);
        self::assertFalse($var->isDynamic());

        $var->resetMeta(Phel::map(Phel::keyword('dynamic'), true));

        self::assertTrue($var->isDynamic());
    }
}
