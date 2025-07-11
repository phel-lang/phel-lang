<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Lang\Keyword;
use Phel\Printer\TypePrinter\KeywordPrinter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class KeywordPrinterTest extends TestCase
{
    #[DataProvider('printerDataProvider')]
    public function test_print(string $expected, Keyword $keyword): void
    {
        self::assertSame(
            $expected,
            (new KeywordPrinter())->print($keyword),
        );
    }

    public static function printerDataProvider(): Generator
    {
        yield 'string name' => [
            ':name',
            Keyword::create('name'),
        ];

        yield 'special chars string' => [
            ':\\?#__\|\/',
            Keyword::create('\\?#__\|\/'),
        ];
    }
}
