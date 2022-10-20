<?php

declare(strict_types=1);

namespace PhelTest\Integration\Phel;

use Phel\Phel;
use PHPUnit\Framework\TestCase;

final class PhelTest extends TestCase
{
    private static mixed $originalArgv = [];

    public static function setUpBeforeClass(): void
    {
        self::$originalArgv = $GLOBALS['argv'];
    }

    public static function tearDownAfterClass(): void
    {
        $GLOBALS['argv'] = self::$originalArgv;
    }

    public function test_globals_argv_as_string_via_run(): void
    {
        Phel::run(__DIR__ . '/../../../../', 'phel\\testing-argv', 'k1=v1 additional');

        self::assertContains('k1=v1', $GLOBALS['argv']);
        self::assertContains('additional', $GLOBALS['argv']);
    }

    public function test_globals_argv_as_array_via_run(): void
    {
        Phel::run(__DIR__ . '/../../../../', 'phel\\testing-argv', ['a', 'b']);

        self::assertContains('a', $GLOBALS['argv']);
        self::assertContains('b', $GLOBALS['argv']);
    }
}
