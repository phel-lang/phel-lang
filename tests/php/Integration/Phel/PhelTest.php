<?php

declare(strict_types=1);

namespace PhelTest\Integration\Phel;

use Phel;
use PHPUnit\Framework\TestCase;

final class PhelTest extends TestCase
{
    public function test_globals_argv_as_array_with_multiple_args_via_run(): void
    {
        Phel::run(__DIR__ . '/../../../../', 'phel\\testing-argv', ['k1=v1', 'additional']);

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

    public function test_setup_runtime_args_sets_program(): void
    {
        Phel::setupRuntimeArgs('my/script.phel', ['arg1', 'arg2']);

        self::assertSame('my/script.phel', Phel::getProgram());
    }

    public function test_setup_runtime_args_sets_argv(): void
    {
        Phel::setupRuntimeArgs('my/script.phel', ['--verbose', 'file.txt']);

        self::assertSame(['--verbose', 'file.txt'], Phel::getArgv());
    }

    public function test_setup_runtime_args_with_empty_argv(): void
    {
        Phel::setupRuntimeArgs('script.phel', []);

        self::assertSame('script.phel', Phel::getProgram());
        self::assertSame([], Phel::getArgv());
    }

    public function test_get_program_returns_empty_string_when_not_set(): void
    {
        unset($GLOBALS['__phel_program']);

        self::assertSame('', Phel::getProgram());
    }

    public function test_get_argv_returns_empty_array_when_not_set(): void
    {
        unset($GLOBALS['__phel_argv']);

        self::assertSame([], Phel::getArgv());
    }

    public function test_argv_does_not_contain_program_name(): void
    {
        Phel::setupRuntimeArgs('my-script.phel', ['--flag', 'value']);

        $argv = Phel::getArgv();
        self::assertNotContains('my-script.phel', $argv);
        self::assertSame('--flag', $argv[0]);
    }
}
