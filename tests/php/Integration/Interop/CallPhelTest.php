<?php

declare(strict_types=1);

namespace PhelTest\Integration\Interop;

use Phel\Build\BuildFacade;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentSingleton;
use PHPUnit\Framework\TestCase;

final class CallPhelTest extends TestCase
{
    private ExampleWrapper $wrapper;

    public function setUp(): void
    {
        GlobalEnvironmentSingleton::initializeNew();

        (new BuildFacade())->compileFile(
            __DIR__ . '/../../../../src/phel/core.phel',
            tempnam(sys_get_temp_dir(), 'phel-core')
        );
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
