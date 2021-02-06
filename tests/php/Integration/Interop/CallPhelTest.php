<?php

declare(strict_types=1);

namespace PhelTest\Integration\Interop;

use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Runtime\RuntimeFactory;
use PHPUnit\Framework\TestCase;

final class CallPhelTest extends TestCase
{
    public function setUp(): void
    {
        RuntimeFactory::initializeNew(new GlobalEnvironment());
    }

    public function testCallOdd(): void
    {
        $wrapper = new ExampleWrapper();

        self::assertTrue($wrapper->isOdd(1));
        self::assertFalse($wrapper->isOdd(2));
    }
}
