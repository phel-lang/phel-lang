<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Test;

use Generator;
use Phel\Command\Test\TestCommandOptions;
use PHPUnit\Framework\TestCase;

final class TestCommandOptionsTest extends TestCase
{
    public function testEmptyFilter(): void
    {
        $options = TestCommandOptions::empty();

        self::assertSame('{:filter nil}', $options->asPhelHashMap());
    }

    /**
     * @dataProvider providerRandomFilter
     */
    public function testRandomFilter(array $options, string $expected): void
    {
        $options = TestCommandOptions::fromArray($options);

        self::assertSame($expected, $options->asPhelHashMap());
    }

    public function providerRandomFilter(): Generator
    {
        yield 'simple filter' => [
            'options' => ['--filter=example'],
            'expected' => '{:filter "example"}',
        ];

        yield 'using underscore' => [
            'options' => ['--filter=using_underscore'],
            'expected' => '{:filter "using_underscore"}',
        ];

        yield 'using hyphen will use underscore instead' => [
            'options' => ['--filter=using-hyphen'],
            'expected' => '{:filter "using_hyphen"}',
        ];
    }
}
