<?php

namespace Phel\Lang;

use PHPUnit\Framework\TestCase;

class TableTest extends TestCase
{
    public function testNumberZeroAsValue()
    {
        $t = Table::fromKVs("a", 0);
        $this->assertEquals(0, $t["a"]);
    }
}
