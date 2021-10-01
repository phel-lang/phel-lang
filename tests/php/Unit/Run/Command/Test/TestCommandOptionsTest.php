<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Command\Test;

use Phel\Run\Command\Test\TestCommandOptions;
use PHPUnit\Framework\TestCase;

final class TestCommandOptionsTest extends TestCase
{
    public function test_empty_filter(): void
    {
        $options = TestCommandOptions::empty();

        self::assertSame('{:filter nil}', $options->asPhelHashMap());
    }

    public function test_random_filter(): void
    {
        $options = TestCommandOptions::fromArray(['filter' => 'example']);

        self::assertSame('{:filter "example"}', $options->asPhelHashMap());
    }
}
