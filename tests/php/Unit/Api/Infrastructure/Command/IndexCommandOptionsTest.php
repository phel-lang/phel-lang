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

    public function test_out_is_kept_as_a_deprecated_alias(): void
    {
        $definition = new IndexCommand()->getDefinition();

        self::assertTrue($definition->hasOption('out'), 'old --out stays for back-compat');
        self::assertStringContainsString('deprecated', $definition->getOption('out')->getDescription());
    }
}
