<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Formatter;

use InvalidArgumentException;
use Phel\Lint\Application\Formatter\FormatterRegistry;
use Phel\Lint\Application\Formatter\HumanFormatter;
use Phel\Lint\Application\Formatter\JsonFormatter;
use PHPUnit\Framework\TestCase;

final class FormatterRegistryTest extends TestCase
{
    public function test_it_looks_up_registered_formatters_by_name(): void
    {
        $registry = new FormatterRegistry();
        $registry->register(new HumanFormatter());
        $registry->register(new JsonFormatter());

        self::assertTrue($registry->has('human'));
        self::assertTrue($registry->has('json'));
        self::assertInstanceOf(HumanFormatter::class, $registry->get('human'));
    }

    public function test_it_throws_on_unknown_formatter(): void
    {
        $registry = new FormatterRegistry();

        $this->expectException(InvalidArgumentException::class);
        $registry->get('nope');
    }

    public function test_it_reports_registered_names(): void
    {
        $registry = new FormatterRegistry();
        $registry->register(new HumanFormatter());

        self::assertContains('human', $registry->names());
    }
}
