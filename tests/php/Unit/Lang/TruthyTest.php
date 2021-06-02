<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Phel\Lang\Truthy;
use PHPUnit\Framework\TestCase;

final class TruthyTest extends TestCase
{
    public function test_false(): void
    {
        $this->assertFalse(Truthy::isTruthy(null));
        $this->assertFalse(Truthy::isTruthy(false));
    }
}
