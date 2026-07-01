<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Infrastructure\Command;

use Phel\Run\Infrastructure\Command\TestCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Tester\CommandCompletionTester;

final class TestCommandOptionsWiringTest extends TestCase
{
    public function test_it_declares_repeatable_include_option(): void
    {
        $option = $this->optionFor('include');

        self::assertTrue($option->isArray(), 'include option is repeatable');
        self::assertTrue($option->isValueRequired(), 'include requires a value');
    }

    public function test_it_declares_repeatable_exclude_option(): void
    {
        $option = $this->optionFor('exclude');

        self::assertTrue($option->isArray(), 'exclude option is repeatable');
        self::assertTrue($option->isValueRequired(), 'exclude requires a value');
    }

    public function test_it_declares_repeatable_ns_option(): void
    {
        $option = $this->optionFor('ns');

        self::assertTrue($option->isArray(), 'ns option is repeatable');
        self::assertTrue($option->isValueRequired(), 'ns requires a value');
    }

    public function test_it_declares_repeatable_filter_option(): void
    {
        $option = $this->optionFor('filter');

        self::assertTrue($option->isArray(), 'filter option is now repeatable');
        self::assertTrue($option->isValueRequired(), 'filter requires a value');
    }

    public function test_default_selector_values_are_empty_arrays(): void
    {
        $definition = $this->definition();

        self::assertSame([], $definition->getOption('include')->getDefault());
        self::assertSame([], $definition->getOption('exclude')->getDefault());
        self::assertSame([], $definition->getOption('ns')->getDefault());
        self::assertSame([], $definition->getOption('filter')->getDefault());
    }

    public function test_it_declares_stack_trace_flag(): void
    {
        $option = $this->optionFor('stack-trace');

        self::assertFalse($option->isValueRequired(), 'stack-trace is a flag');
        self::assertFalse($option->getDefault(), 'stack-trace defaults to false');
        self::assertStringContainsString('stack trace', strtolower($option->getDescription()));
    }

    public function test_it_declares_list_flag(): void
    {
        $option = $this->optionFor('list');

        self::assertFalse($option->isValueRequired(), 'list is a flag');
        self::assertFalse($option->getDefault(), 'list defaults to false');
        self::assertStringContainsString('list', strtolower($option->getDescription()));
    }

    public function test_it_declares_last_failed_flag(): void
    {
        $option = $this->optionFor('last-failed');

        self::assertFalse($option->isValueRequired(), 'last-failed is a flag');
        self::assertFalse($option->getDefault(), 'last-failed defaults to false');
        self::assertStringContainsString('failed', strtolower($option->getDescription()));
    }

    public function test_it_declares_slowest_option(): void
    {
        $option = $this->optionFor('slowest');

        self::assertTrue($option->isValueRequired(), 'slowest takes a value');
        self::assertSame(0, $option->getDefault(), 'slowest defaults to 0');
        self::assertStringContainsString('slowest', strtolower($option->getDescription()));
    }

    public function test_it_declares_repeat_option(): void
    {
        $option = $this->optionFor('repeat');

        self::assertTrue($option->isValueRequired(), 'repeat takes a value');
        self::assertSame(1, $option->getDefault(), 'repeat defaults to 1');
        self::assertStringContainsString('flaky', strtolower($option->getDescription()));
    }

    public function test_it_declares_seed_option(): void
    {
        $option = $this->optionFor('seed');

        self::assertTrue($option->isValueRequired(), 'seed takes a value');
        self::assertNull($option->getDefault(), 'seed defaults to null');
        self::assertStringContainsString('seed', strtolower($option->getDescription()));
    }

    public function test_it_declares_random_order_flag(): void
    {
        $option = $this->optionFor('random-order');

        self::assertFalse($option->isValueRequired(), 'random-order is a flag');
        self::assertFalse($option->getDefault(), 'random-order defaults to false');
        self::assertStringContainsString('shuffle', strtolower($option->getDescription()));
    }

    public function test_description_mentions_selector_semantics(): void
    {
        $include = $this->optionFor('include');
        $exclude = $this->optionFor('exclude');
        $ns = $this->optionFor('ns');

        self::assertStringContainsString('tag', strtolower($include->getDescription()));
        self::assertStringContainsString('skip', strtolower($exclude->getDescription()));
        self::assertStringContainsString('glob', strtolower($ns->getDescription()));
    }

    public function test_output_option_has_o_short_alias(): void
    {
        // CLI flag convention: --output is -o across commands.
        self::assertSame('o', $this->optionFor('output')->getShortcut());
    }

    public function test_coverage_option_completes_formats(): void
    {
        $tester = new CommandCompletionTester(new TestCommand());

        self::assertSame(['text', 'clover', 'html'], $tester->complete(['--coverage', '']));
    }

    public function test_reporter_option_completes_builtins(): void
    {
        $tester = new CommandCompletionTester(new TestCommand());

        self::assertSame(
            ['default', 'testdox', 'dot', 'tap', 'junit-xml'],
            $tester->complete(['--reporter', '']),
        );
    }

    public function test_parallel_option_completes_keywords(): void
    {
        $tester = new CommandCompletionTester(new TestCommand());

        self::assertSame(['auto', 'max'], $tester->complete(['--parallel', '']));
    }

    private function definition(): InputDefinition
    {
        $command = new TestCommand();

        return $command->getDefinition();
    }

    private function optionFor(string $name): InputOption
    {
        return $this->definition()->getOption($name);
    }
}
