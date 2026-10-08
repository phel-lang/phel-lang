<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Formatter;

use InvalidArgumentException;
use Phel\Lint\Application\Formatter\FormatterRegistry;
use Phel\Lint\Application\Formatter\JsonFormatter;
use Phel\Lint\Application\Formatter\TextFormatter;
use PHPUnit\Framework\TestCase;

final class FormatterRegistryTest extends TestCase
{
    public function test_it_looks_up_registered_formatters_by_name(): void
    {
        $registry = new FormatterRegistry();
        $registry->register(new TextFormatter());
        $registry->register(new JsonFormatter());

        self::assertTrue($registry->has('text'));
        self::assertTrue($registry->has('json'));
        self::assertInstanceOf(TextFormatter::class, $registry->get('text'));
    }

    public function test_it_throws_on_unknown_formatter(): void
    {
        $registry = new FormatterRegistry();

        $this->expectException(InvalidArgumentException::class);
        $registry->get('nope');
    }

    /**
     * The message is what a user typing `--format=nope` sees, so it has to
     * name both the bad input and the accepted values.
     */
    public function test_the_unknown_formatter_message_names_the_input_and_the_alternatives(): void
    {
        $registry = new FormatterRegistry();
        $registry->register(new TextFormatter());
        $registry->register(new JsonFormatter());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown lint formatter: nope. Known: text, json.');
        $registry->get('nope');
    }

    public function test_it_reports_registered_names(): void
    {
        $registry = new FormatterRegistry();
        $registry->register(new TextFormatter());

        self::assertContains('text', $registry->names());
    }
}
