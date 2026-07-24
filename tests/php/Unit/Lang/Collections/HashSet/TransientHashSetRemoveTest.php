<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\HashSet;

use Phel\Lang\Collections\HashSet\TransientHashSetInterface;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

/**
 * `TransientHashSetInterface::remove()` is the mechanism behind
 * `phel.core/disj!` (`(php/-> tcoll (remove value))` in
 * `src/phel/core/transients.phel`), which dispatches on this interface. The
 * call lives in Phel source, so it is invisible to a PHP-symbol grep; this test
 * pins the contract and keeps it paired with the persistent
 * `PersistentHashSetInterface::remove()` that `phel.core/disj` uses.
 */
final class TransientHashSetRemoveTest extends TestCase
{
    public function test_remove_drops_the_value_in_place(): void
    {
        $transient = $this->transientSetOf(1, 2, 3);

        $returned = $transient->remove(2);

        self::assertSame($transient, $returned, 'remove() mutates in place and returns the same transient');
        self::assertFalse($transient->contains(2));
        self::assertTrue($transient->contains(1));
        self::assertTrue($transient->contains(3));
        self::assertCount(2, $transient);
    }

    public function test_remove_of_an_absent_value_is_a_no_op(): void
    {
        $transient = $this->transientSetOf(1, 2);

        $transient->remove(42);

        self::assertCount(2, $transient);
    }

    public function test_remove_is_visible_on_the_persistent_result(): void
    {
        $transient = $this->transientSetOf(1, 2, 3);
        $transient->remove(1);
        $transient->remove(3);

        $persistent = $transient->persistent();

        self::assertCount(1, $persistent);
        self::assertTrue($persistent->contains(2));
        self::assertFalse($persistent->contains(1));
    }

    public function test_remove_can_empty_the_set(): void
    {
        $transient = $this->transientSetOf(1, 2);
        $transient->remove(1)->remove(2);

        self::assertCount(0, $transient);
        self::assertCount(0, $transient->persistent());
    }

    public function test_operation_is_declared_on_the_interface_not_only_the_class(): void
    {
        self::assertTrue(
            method_exists(TransientHashSetInterface::class, 'remove'),
            'phel.core/disj! dispatches on TransientHashSetInterface and calls remove() on it; '
            . 'keep it on the interface so the transient side mirrors PersistentHashSetInterface.',
        );
    }

    /**
     * @return TransientHashSetInterface<mixed>
     */
    private function transientSetOf(mixed ...$values): TransientHashSetInterface
    {
        return TypeFactory::getInstance()
            ->persistentHashSetFromArray($values)
            ->asTransient();
    }
}
