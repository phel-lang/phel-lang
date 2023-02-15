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

        self::assertSame('{:filter nil :testdox false}', $options->asPhelHashMap());
    }

    public function test_random_filter(): void
    {
        $options = TestCommandOptions::fromArray(['filter' => 'example']);

        self::assertSame('{:filter "example" :testdox false}', $options->asPhelHashMap());
    }

    public function test_no_testdox(): void
    {
        $options = TestCommandOptions::fromArray([]);

        self::assertSame('{:filter nil :testdox false}', $options->asPhelHashMap());
    }

    public function test_false_testdox(): void
    {
        $options = TestCommandOptions::fromArray(['testdox' => false]);

        self::assertSame('{:filter nil :testdox false}', $options->asPhelHashMap());
    }

    public function test_null_testdox(): void
    {
        $options = TestCommandOptions::fromArray(['testdox' => null]);

        self::assertSame('{:filter nil :testdox false}', $options->asPhelHashMap());
    }

    public function test_true_testdox(): void
    {
        $options = TestCommandOptions::fromArray(['testdox' => 'true']);

        self::assertSame('{:filter nil :testdox true}', $options->asPhelHashMap());
    }
}
