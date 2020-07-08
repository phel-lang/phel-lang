<?php

declare(strict_types=1);

namespace PhelTest\Lang;

use Phel\Lang\Table;
use PHPUnit\Framework\TestCase;

final class TableTest extends TestCase
{
    public function testNumberZeroAsValue(): void
    {
        $table = Table::fromKVs('a', 0);
        $this->assertEquals(0, $table['a']);
    }
}
