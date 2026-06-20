<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\Console;

use Phel\Shared\Console\DeprecatedOptionWarner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class DeprecatedOptionWarnerTest extends TestCase
{
    public function test_writes_notice_naming_old_and_new_option(): void
    {
        // Plain (non-console) output: falls back to the single stream.
        $output = new BufferedOutput();

        DeprecatedOptionWarner::warn($output, 'out', 'output');

        $text = $output->fetch();
        self::assertStringContainsString('--out is deprecated', $text);
        self::assertStringContainsString('use --output', $text);
    }
}
