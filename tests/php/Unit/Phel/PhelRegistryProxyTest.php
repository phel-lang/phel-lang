<?php

declare(strict_types=1);

namespace PhelTest\Unit\Phel;

use BadMethodCallException;
use Phel;
use PHPUnit\Framework\TestCase;

final class PhelRegistryProxyTest extends TestCase
{
    public function test_calls_registry_when_method_is_not_defined(): void
    {
        Phel::clear();
        Phel::addDefinition('ns', 'name', 'value');

        self::assertTrue(Phel::hasDefinition('ns', 'name'));
        self::assertSame(['name' => 'value'], Phel::getDefinitionInNamespace('ns'));
    }

    public function test_throws_exception_when_method_does_not_exist(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Method "nonExistingMethod" does not exist');

        Phel::nonExistingMethod();
    }
}
