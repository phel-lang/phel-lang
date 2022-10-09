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

    public function test_globals_argv_via_run(): void
    {
        Phel::run(__DIR__ . '/../../../../', 'phel\\testing-argv', 'foo=bar baz');

        self::assertContains('foo=bar', $GLOBALS['argv']);
        self::assertContains('baz', $GLOBALS['argv']);
    }
}
