<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\HashSet\TransientHashSetInterface;
use Phel\Lang\TypeFactory;
use Phel\Printer\Printer;
use Phel\Printer\TypePrinter\HashSetPrinter;
use PHPUnit\Framework\TestCase;

final class HashSetPrinterTest extends TestCase
{
    /**
     * @dataProvider providerPersistentHashSet
     * @dataProvider providerTransientHashSet
     *
     * @param PersistentHashSetInterface|TransientHashSetInterface $hashSet
     */
    public function testPrintHashSet(string $expected, $hashSet): void
    {
        self::assertSame(
            $expected,
            (new HashSetPrinter(Printer::readable()))->print($hashSet)
        );
    }

    public function providerPersistentHashSet(): Generator
    {
        $set = TypeFactory::getInstance()->emptyPersistentHashSet();

        yield 'empty set' => [
            'expected' => '(set)',
            'set' => $set,
        ];

        yield 'set with one value' => [
            'expected' => '(set "name")',
            'set' => $set->add('name'),
        ];

        yield 'set with multiple values' => [
            'expected' => '(set "key1" "key2")',
            'set' => $set->add('key1')->add('key2'),
        ];
    }

    public function providerTransientHashSet(): Generator
    {
        $set = TypeFactory::getInstance()->emptyPersistentHashSet();

        yield 'empty set' => [
            'expected' => '(set)',
            'set' => $set->asTransient(),
        ];

        yield 'set with one value' => [
            'expected' => '(set "name")',
            'set' => $set->add('name')->asTransient(),
        ];

        yield 'set with multiple values' => [
            'expected' => '(set "key1" "key2")',
            'set' => $set->add('key1')->add('key2')->asTransient(),
        ];
    }
}
