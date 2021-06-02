<?php

declare(strict_types=1);

namespace PhelTest\Integration\Interop;

use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Runtime\RuntimeSingleton;
use PHPUnit\Framework\TestCase;

final class CallPhelTest extends TestCase
{
    private ExampleWrapper $wrapper;

    public function setUp(): void
    {
        RuntimeSingleton::initializeNew(new GlobalEnvironment());

        $this->wrapper = new ExampleWrapper();
    }

    public function test_call_odd(): void
    {
        self::assertTrue($this->wrapper->isOdd(1));
        self::assertFalse($this->wrapper->isOdd(2));
    }

    public function test_call_print_str(): void
    {
        self::assertSame('a b c', $this->wrapper->printStr('a', 'b', 'c'));
    }
}
