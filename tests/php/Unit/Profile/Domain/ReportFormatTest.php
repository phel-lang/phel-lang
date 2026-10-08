<?php

declare(strict_types=1);

namespace PhelTest\Unit\Profile\Domain;

use Phel\Profile\Domain\ReportFormat;
use PHPUnit\Framework\TestCase;

final class ReportFormatTest extends TestCase
{
    public function test_it_reads_each_format_by_name(): void
    {
        self::assertSame(ReportFormat::Text, ReportFormat::fromOption('text'));
        self::assertSame(ReportFormat::Json, ReportFormat::fromOption('json'));
        self::assertSame(ReportFormat::Both, ReportFormat::fromOption('both'));
    }

    public function test_the_former_table_name_still_reads_as_text(): void
    {
        self::assertSame(ReportFormat::Text, ReportFormat::fromOption('table'));
    }

    public function test_an_unknown_name_reads_as_nothing(): void
    {
        self::assertNull(ReportFormat::fromOption('xml'));
    }
}
