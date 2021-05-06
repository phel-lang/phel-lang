<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Map\TransientMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\TypeFactory;
use Phel\Printer\Printer;
use Phel\Printer\TypePrinter\MapPrinter;
use PHPUnit\Framework\TestCase;

final class MapPrinterTest extends TestCase
{
    /**
     * @dataProvider providerPersistentMap
     * @dataProvider providerTransientMap
     *
     * @param PersistentMapInterface|TransientMapInterface $map
     */
    public function testPrintMap(string $expected, $map): void
    {
        self::assertSame(
            $expected,
            (new MapPrinter(Printer::readable()))->print($map)
        );
    }

    public function providerPersistentMap(): Generator
    {
        $map = TypeFactory::getInstance()->emptyPersistentMap();

        yield 'empty persistent map' => [
            'expected' => '{}',
            'map' => $map,
        ];

        yield 'persistent using a number as key' => [
            'expected' => '{1 "value"}',
            'map' => $map->put(1, 'value'),
        ];

        yield 'persistent using a string as key' => [
            'expected' => '{"key" "value"}',
            'map' => $map->put('key', 'value'),
        ];

        yield 'persistent using a keyword as key' => [
            'expected' => '{:key "value"}',
            'map' => $map->put(Keyword::create('key'), 'value'),
        ];

        yield 'persistent using multiple key-values' => [
            'expected' => '{"k1" "v1" "k2" "v2"}',
            'map' => $map->put('k1', 'v1')->put('k2', 'v2'),
        ];
    }

    public function providerTransientMap(): Generator
    {
        $map = TypeFactory::getInstance()->emptyPersistentMap();

        yield 'empty transient map' => [
            'expected' => '{}',
            'map' => $map->asTransient(),
        ];

        yield 'transient using a number as key' => [
            'expected' => '{1 "value"}',
            'map' => $map->put(1, 'value')->asTransient(),
        ];

        yield 'transient using a string as key' => [
            'expected' => '{"key" "value"}',
            'map' => $map->put('key', 'value')->asTransient(),
        ];

        yield 'transient using a keyword as key' => [
            'expected' => '{:key "value"}',
            'map' => $map->put(Keyword::create('key'), 'value')->asTransient(),
        ];

        yield 'transient using multiple key-values' => [
            'expected' => '{"k1" "v1" "k2" "v2"}',
            'map' => $map->put('k1', 'v1')->put('k2', 'v2')->asTransient(),
        ];
    }
}
