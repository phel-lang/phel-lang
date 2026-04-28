<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Infrastructure\Command;

use Phel\Run\Infrastructure\Command\TestCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

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

    public function test_description_mentions_selector_semantics(): void
    {
        $include = $this->optionFor('include');
        $exclude = $this->optionFor('exclude');
        $ns = $this->optionFor('ns');

        self::assertStringContainsString('tag', strtolower($include->getDescription()));
        self::assertStringContainsString('skip', strtolower($exclude->getDescription()));
        self::assertStringContainsString('glob', strtolower($ns->getDescription()));
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
