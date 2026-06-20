<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Generator;
use Phel\Shared\Printer\TypePrinter\DateTimePrinter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateTimePrinterTest extends TestCase
{
    #[DataProvider('providerPrint')]
    public function test_print(DateTimeInterface $form, string $expected): void
    {
        self::assertSame($expected, new DateTimePrinter()->print($form));
    }

    public static function providerPrint(): Generator
    {
        $utc = new DateTimeZone('UTC');

        yield 'whole seconds keep no fraction' => [
            new DateTimeImmutable('2026-06-17T00:00:00', $utc),
            '#inst "2026-06-17T00:00:00+00:00"',
        ];

        yield 'microseconds preserved' => [
            new DateTimeImmutable('2026-06-17T08:30:15.123456', $utc),
            '#inst "2026-06-17T08:30:15.123456+00:00"',
        ];

        yield 'non-utc offset preserved' => [
            new DateTimeImmutable('2026-06-17T08:30:15', new DateTimeZone('+02:00')),
            '#inst "2026-06-17T08:30:15+02:00"',
        ];

        yield 'mutable DateTime also prints' => [
            new DateTime('2026-06-17T00:00:00', $utc),
            '#inst "2026-06-17T00:00:00+00:00"',
        ];
    }
}
