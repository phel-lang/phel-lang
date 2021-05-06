<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\TypeFactory;
use Phel\Printer\Printer;
use Phel\Printer\TypePrinter\PersistentMapPrinter;
use PHPUnit\Framework\TestCase;

final class MapPrinterTest extends TestCase
{
    /**
     * @dataProvider providerPersistentMap
     */
    public function testPersistentMap(string $expected, PersistentMapInterface $map): void
    {
        self::assertSame(
            $expected,
            (new PersistentMapPrinter(Printer::readable()))->print($map)
        );
    }

    public function providerPersistentMap(): Generator
    {
        $map = TypeFactory::getInstance()->emptyPersistentMap();

        yield 'empty map' => [
            'expected' => '{}',
            'map' => $map,
        ];

        yield 'map with one key:value' => [
            'expected' => '{:key "value"}',
            'map' => $map->put(Keyword::create('key'), 'value'),
        ];
    }
}
