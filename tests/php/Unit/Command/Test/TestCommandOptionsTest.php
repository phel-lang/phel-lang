<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Test;

use Phel\Command\Test\TestCommandOptions;
use PHPUnit\Framework\TestCase;

final class TestCommandOptionsTest extends TestCase
{
    public function testEmptyFilter(): void
    {
        $options = TestCommandOptions::empty();

        self::assertSame('{:filter nil}', $options->asPhelHashMap());
    }

    public function testRandomFilter(): void
    {
        $options = TestCommandOptions::fromArray(['filter' => 'example']);

        self::assertSame('{:filter "example"}', $options->asPhelHashMap());
    }
}
