<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Phel\Lang\Registry;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase
{
    private Registry $registry;

    public static function tearDownAfterClass(): void
    {
        Registry::getInstance()->clear();
    }

    protected function setUp(): void
    {
        $this->registry = Registry::getInstance();
        $this->registry->clear();
    }

    public function test_null_when_non_existing_definition_by_value(): void
    {
        $actual = $this->registry->getDefinition('ns', 'non-existing');

        self::assertNull($actual);
    }

    public function test_value_definition(): void
    {
        $this->registry->addDefinition('ns', 'array', [1, 2, 3]);
        $this->registry->getDefinition('ns', 'array')[] = 4;
        $actual = $this->registry->getDefinition('ns', 'array');

        self::assertSame([1, 2, 3], $actual);
    }

    public function test_error_when_non_existing_definition_by_reference(): void
    {
        $this->expectExceptionMessage('Only variables can be returned by reference');

        $this->registry->getDefinitionReference('ns', 'non-existing');
    }

    public function test_reference_definition(): void
    {
        $this->registry->addDefinition('ns', 'array', [1, 2, 3]);
        $this->registry->getDefinitionReference('ns', 'array')[] = 4;
        $actual = $this->registry->getDefinition('ns', 'array');

        self::assertSame([1, 2, 3, 4], $actual);
    }
}
