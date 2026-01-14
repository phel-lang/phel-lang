<?php

declare(strict_types=1);

namespace PhelTest\Integration\Phel;

use Phel;
use PHPUnit\Framework\TestCase;

final class PhelTest extends TestCase
{
    public function test_globals_argv_as_string_via_run(): void
    {
        Phel::run(__DIR__ . '/../../../../', 'phel\\testing-argv', 'k1=v1 additional');

        self::assertContains('k1=v1', $GLOBALS['__phel_argv']);
        self::assertContains('additional', $GLOBALS['__phel_argv']);
    }

    public function test_globals_argv_as_array_via_run(): void
    {
        Phel::run(__DIR__ . '/../../../../', 'phel\\testing-argv', ['a', 'b']);

        self::assertContains('a', $GLOBALS['__phel_argv']);
        self::assertContains('b', $GLOBALS['__phel_argv']);
    }

    public function test_program_is_set_via_run(): void
    {
        Phel::run(__DIR__ . '/../../../../', 'phel\\testing-argv', []);

        self::assertSame('phel\\testing-argv', $GLOBALS['__phel_program']);
    }
}
