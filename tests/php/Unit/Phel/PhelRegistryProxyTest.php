<?php

declare(strict_types=1);

namespace PhelTest\Unit\Phel;

use Phel;
use Phel\Lang\Registry;
use PHPUnit\Framework\TestCase;

final class PhelRegistryProxyTest extends TestCase
{
    public function test_calls_registry_when_method_is_not_defined(): void
    {
        $registry = Registry::getInstance();
        $registry->clear();

        Phel::addDefinition('ns', 'name', 'value');

        self::assertTrue(Phel::hasDefinition('ns', 'name'));
        self::assertSame(['name' => 'value'], Phel::getDefinitionInNamespace('ns'));
    }
}
