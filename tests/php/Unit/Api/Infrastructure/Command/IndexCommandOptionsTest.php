<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Infrastructure\Command;

use Phel\Api\Infrastructure\Command\IndexCommand;
use PHPUnit\Framework\TestCase;

final class IndexCommandOptionsTest extends TestCase
{
    public function test_output_is_canonical_with_o_short_alias(): void
    {
        $definition = new IndexCommand()->getDefinition();

        self::assertTrue($definition->hasOption('output'));
        self::assertSame('o', $definition->getOption('output')->getShortcut());
    }

    public function test_the_removed_out_alias_is_gone(): void
    {
        $definition = new IndexCommand()->getDefinition();

        self::assertFalse($definition->hasOption('out'));
    }
}
