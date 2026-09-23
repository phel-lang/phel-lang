<?php

declare(strict_types=1);

namespace PhelTest\Unit\Phel;

use BadMethodCallException;
use Phel;
use Phel\Lang\DynamicScope;
use PHPUnit\Framework\TestCase;

final class PhelRegistryProxyTest extends TestCase
{
    protected function tearDown(): void
    {
        DynamicScope::getInstance()->clear();
    }

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

    public function test_get_definition_returns_registry_value_when_no_binding_active(): void
    {
        Phel::clear();
        DynamicScope::getInstance()->clear();
        Phel::addDefinition('ns', 'x', 'root');

        // No dynamic binding ever established: the latch is off and the read
        // returns the registry (root) value without consulting the scope.
        self::assertFalse(DynamicScope::$anyActive);
        self::assertSame('root', Phel::getDefinition('ns', 'x'));
    }

    public function test_get_definition_returns_bound_value_inside_dynamic_binding(): void
    {
        Phel::clear();
        DynamicScope::getInstance()->clear();
        Phel::addDefinition('ns', 'x', 'root');

        $seen = DynamicScope::getInstance()->withFrame(
            ['ns/x' => 'bound'],
            static fn(): mixed => Phel::getDefinition('ns', 'x'),
        );

        self::assertSame('bound', $seen, 'dynamic binding overrides the root read');
        // Outside the binding the root value is seen again.
        self::assertSame('root', Phel::getDefinition('ns', 'x'));
    }
}
