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

    public function test_include_tag_list(): void
    {
        $options = TestCommandOptions::fromArray([
            TestCommandOptions::INCLUDE => ['integration', 'smoke'],
        ]);

        self::assertSame(
            '{:filter nil :testdox false :fail-fast false :include [:integration :smoke]}',
            $options->asPhelHashMap(),
        );
    }

    public function test_exclude_tag_list(): void
    {
        $options = TestCommandOptions::fromArray([
            TestCommandOptions::EXCLUDE => ['slow'],
        ]);

        self::assertSame(
            '{:filter nil :testdox false :fail-fast false :exclude [:slow]}',
            $options->asPhelHashMap(),
        );
    }

    public function test_ns_pattern_list(): void
    {
        $options = TestCommandOptions::fromArray([
            TestCommandOptions::NS_PATTERNS => ['phel.http.*', 'phel.json.**'],
        ]);

        self::assertSame(
            '{:filter nil :testdox false :fail-fast false :ns-patterns ["phel.http.*" "phel.json.**"]}',
            $options->asPhelHashMap(),
        );
    }

    public function test_filters_list(): void
    {
        $options = TestCommandOptions::fromArray([
            TestCommandOptions::FILTERS => ['foo-'],
        ]);

        self::assertSame(
            '{:filter nil :testdox false :fail-fast false :filters ["foo-"]}',
            $options->asPhelHashMap(),
        );
    }

    public function test_all_selectors_combined(): void
    {
        $options = TestCommandOptions::fromArray([
            TestCommandOptions::INCLUDE => ['integration'],
            TestCommandOptions::EXCLUDE => ['slow'],
            TestCommandOptions::NS_PATTERNS => ['phel.http.*'],
            TestCommandOptions::FILTERS => ['get-'],
        ]);

        self::assertSame(
            '{:filter nil :testdox false :fail-fast false :include [:integration] :exclude [:slow] :ns-patterns ["phel.http.*"] :filters ["get-"]}',
            $options->asPhelHashMap(),
        );
    }

    public function test_selector_empty_strings_are_dropped(): void
    {
        $options = TestCommandOptions::fromArray([
            TestCommandOptions::INCLUDE => ['integration', '', 'smoke'],
            TestCommandOptions::EXCLUDE => [''],
            TestCommandOptions::NS_PATTERNS => ['', 'phel.http.*'],
            TestCommandOptions::FILTERS => [''],
        ]);

        self::assertSame(
            '{:filter nil :testdox false :fail-fast false :include [:integration :smoke] :ns-patterns ["phel.http.*"]}',
            $options->asPhelHashMap(),
        );
    }

    public function test_non_array_selector_is_ignored(): void
    {
        $options = TestCommandOptions::fromArray([
            TestCommandOptions::INCLUDE => 'not-an-array',
            TestCommandOptions::EXCLUDE => null,
        ]);

        self::assertSame(
            '{:filter nil :testdox false :fail-fast false}',
            $options->asPhelHashMap(),
        );
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
