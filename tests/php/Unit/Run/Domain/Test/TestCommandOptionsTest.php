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

    public function test_include_and_exclude_roundtrip_preserves_all_keys(): void
    {
        $input = [
            TestCommandOptions::FILTER => 'add-',
            TestCommandOptions::TESTDOX => true,
            TestCommandOptions::FAIL_FAST => true,
            TestCommandOptions::REPORTERS => ['dot', 'junit-xml'],
            TestCommandOptions::JUNIT_OUTPUT => 'build/junit.xml',
            TestCommandOptions::INCLUDE => ['integration'],
            TestCommandOptions::EXCLUDE => ['slow'],
            TestCommandOptions::NS_PATTERNS => ['phel.http.**'],
            TestCommandOptions::FILTERS => ['get-'],
        ];

        $printed = TestCommandOptions::fromArray($input)->asPhelHashMap();

        self::assertStringContainsString(':filter "add-"', $printed);
        self::assertStringContainsString(':testdox true', $printed);
        self::assertStringContainsString(':fail-fast true', $printed);
        self::assertStringContainsString(':reporters [:dot :junit-xml]', $printed);
        self::assertStringContainsString(':junit-output "build/junit.xml"', $printed);
        self::assertStringContainsString(':include [:integration]', $printed);
        self::assertStringContainsString(':exclude [:slow]', $printed);
        self::assertStringContainsString(':ns-patterns ["phel.http.**"]', $printed);
        self::assertStringContainsString(':filters ["get-"]', $printed);
    }

    public function test_empty_then_populated_produces_stable_shape(): void
    {
        $empty = TestCommandOptions::empty();
        $populated = TestCommandOptions::fromArray([
            TestCommandOptions::INCLUDE => ['integration'],
        ]);

        self::assertStringStartsWith('{:filter nil :testdox false :fail-fast false', $empty->asPhelHashMap());
        self::assertStringStartsWith('{:filter nil :testdox false :fail-fast false', $populated->asPhelHashMap());
    }

    public function test_selector_values_survive_exact_string_equality_check(): void
    {
        // Regression-style coverage: every key that arrives from the CLI must
        // appear in the printed hash-map exactly once, in the order the
        // generator produces.
        $printed = TestCommandOptions::fromArray([
            TestCommandOptions::INCLUDE => ['a', 'b'],
            TestCommandOptions::EXCLUDE => ['c'],
            TestCommandOptions::NS_PATTERNS => ['x.*'],
            TestCommandOptions::FILTERS => ['y'],
        ])->asPhelHashMap();

        self::assertSame(
            '{:filter nil :testdox false :fail-fast false :include [:a :b] :exclude [:c] :ns-patterns ["x.*"] :filters ["y"]}',
            $printed,
        );
    }

    public function test_filter_with_quote_character_is_escaped_by_printer(): void
    {
        $options = TestCommandOptions::fromArray([
            TestCommandOptions::FILTER => 'has "quotes"',
        ]);

        // The Printer must render the string with escaped quotes so that the
        // generated Phel expression is syntactically valid.
        self::assertStringContainsString(':filter "has \\"quotes\\""', $options->asPhelHashMap());
    }
}
