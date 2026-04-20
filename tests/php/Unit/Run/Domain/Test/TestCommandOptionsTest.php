<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Test;

use Phel\Run\Domain\Test\TestCommandOptions;
use PHPUnit\Framework\TestCase;

final class TestCommandOptionsTest extends TestCase
{
    public function test_empty_filter(): void
    {
        $options = TestCommandOptions::empty();

        self::assertSame('{:filter nil :testdox false :fail-fast false}', $options->asPhelHashMap());
    }

    public function test_random_filter(): void
    {
        $options = TestCommandOptions::fromArray(['filter' => 'example']);

        self::assertSame('{:filter "example" :testdox false :fail-fast false}', $options->asPhelHashMap());
    }

    public function test_no_testdox(): void
    {
        $options = TestCommandOptions::fromArray([]);

        self::assertSame('{:filter nil :testdox false :fail-fast false}', $options->asPhelHashMap());
    }

    public function test_false_testdox(): void
    {
        $options = TestCommandOptions::fromArray(['testdox' => false]);

        self::assertSame('{:filter nil :testdox false :fail-fast false}', $options->asPhelHashMap());
    }

    public function test_null_testdox(): void
    {
        $options = TestCommandOptions::fromArray(['testdox' => null]);

        self::assertSame('{:filter nil :testdox false :fail-fast false}', $options->asPhelHashMap());
    }

    public function test_true_testdox(): void
    {
        $options = TestCommandOptions::fromArray(['testdox' => 'true']);

        self::assertSame('{:filter nil :testdox true :fail-fast false}', $options->asPhelHashMap());
    }

    public function test_fail_fast(): void
    {
        $options = TestCommandOptions::fromArray(['fail-fast' => true]);

        self::assertSame('{:filter nil :testdox false :fail-fast true}', $options->asPhelHashMap());
    }

    public function test_single_reporter(): void
    {
        $options = TestCommandOptions::fromArray(['reporters' => ['dot']]);

        self::assertSame(
            '{:filter nil :testdox false :fail-fast false :reporters [:dot]}',
            $options->asPhelHashMap(),
        );
    }

    public function test_multiple_reporters(): void
    {
        $options = TestCommandOptions::fromArray(['reporters' => ['dot', 'junit-xml']]);

        self::assertSame(
            '{:filter nil :testdox false :fail-fast false :reporters [:dot :junit-xml]}',
            $options->asPhelHashMap(),
        );
    }

    public function test_reporters_filter_out_empty_strings(): void
    {
        $options = TestCommandOptions::fromArray(['reporters' => ['dot', '', 'tap']]);

        self::assertSame(
            '{:filter nil :testdox false :fail-fast false :reporters [:dot :tap]}',
            $options->asPhelHashMap(),
        );
    }

    public function test_junit_output_emitted_when_path_is_set(): void
    {
        $options = TestCommandOptions::fromArray([
            'reporters' => ['junit-xml'],
            'junit-output' => 'build/junit.xml',
        ]);

        self::assertSame(
            '{:filter nil :testdox false :fail-fast false :reporters [:junit-xml] :junit-output "build/junit.xml"}',
            $options->asPhelHashMap(),
        );
    }

    public function test_junit_output_empty_string_is_ignored(): void
    {
        $options = TestCommandOptions::fromArray([
            'reporters' => ['junit-xml'],
            'junit-output' => '',
        ]);

        self::assertSame(
            '{:filter nil :testdox false :fail-fast false :reporters [:junit-xml]}',
            $options->asPhelHashMap(),
        );
    }
}
