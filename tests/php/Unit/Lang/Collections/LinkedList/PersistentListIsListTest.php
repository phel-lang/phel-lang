<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\LinkedList;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

/**
 * `PersistentListInterface::isList()` is the mechanism behind `phel.core/list?`
 * (`(php/-> x (isList))` in `src/phel/core/predicates.phel`). Because the call
 * lives in Phel source it is invisible to a PHP-symbol grep, so this test pins
 * both the contract and the fact that it is reached through the interface, not
 * through a concrete class.
 */
final class PersistentListIsListTest extends TestCase
{
    public function test_list_literals_are_lists(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([1, 2, 3]);

        self::assertInstanceOf(PersistentListInterface::class, $list);
        self::assertTrue($list->isList());
    }

    public function test_empty_list_is_a_list(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([]);

        self::assertInstanceOf(PersistentListInterface::class, $list);
        self::assertTrue($list->isList());
    }

    public function test_seq_views_are_not_lists(): void
    {
        $seq = TypeFactory::getInstance()->persistentSeqListFromArray([1, 2, 3]);

        self::assertInstanceOf(PersistentListInterface::class, $seq);
        self::assertFalse($seq->isList());
    }

    public function test_empty_seq_view_is_not_a_list(): void
    {
        $seq = TypeFactory::getInstance()->persistentSeqListFromArray([]);

        self::assertInstanceOf(PersistentListInterface::class, $seq);
        self::assertFalse($seq->isList());
    }

    public function test_seq_view_stays_a_non_list_through_cons_and_pop(): void
    {
        $seq = TypeFactory::getInstance()->persistentSeqListFromArray([1, 2, 3]);

        self::assertFalse($seq->cons(0)->isList());
        self::assertFalse($seq->pop()->isList());
        self::assertFalse($seq->concat([4, 5])->isList());
    }

    public function test_list_stays_a_list_through_cons_and_pop(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([1, 2, 3]);

        self::assertTrue($list->cons(0)->isList());
        self::assertTrue($list->pop()->isList());
        self::assertTrue($list->concat([4, 5])->isList());
    }

    public function test_flag_is_declared_on_the_interface_not_only_the_class(): void
    {
        self::assertTrue(
            method_exists(PersistentListInterface::class, 'isList'),
            'phel.core/list? calls isList() on PersistentListInterface; keep it on the interface '
            . 'so third-party implementations can answer list? correctly.',
        );
    }
}
