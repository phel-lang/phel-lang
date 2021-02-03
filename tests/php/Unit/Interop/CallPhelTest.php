<?php

declare(strict_types=1);

namespace PhelTest\Unit\Interop;

use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Runtime\RuntimeFactory;
use PHPUnit\Framework\TestCase;

class CallPhelTest extends TestCase
{
    public function testCallOdd(): void
    {
        RuntimeFactory::initializeNew(new GlobalEnvironment());
        $wrapperMock = new ExampleWrapper();

        self::assertTrue($wrapperMock->isOdd(1));
        self::assertFalse($wrapperMock->isOdd(2));
    }
}
