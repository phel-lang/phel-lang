<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Phel\Lang\Registry;
use Phel\Lang\VarReference;
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
        $this->expectExceptionMessage('Definition "ns/non-existing" not found');

        $this->registry->getDefinitionReference('ns', 'non-existing');
    }

    public function test_reference_definition(): void
    {
        $this->registry->addDefinition('ns', 'array', [1, 2, 3]);
        $this->registry->getDefinitionReference('ns', 'array')[] = 4;
        $actual = $this->registry->getDefinition('ns', 'array');

        self::assertSame([1, 2, 3, 4], $actual);
    }

    public function test_add_definition_returns_var_reference(): void
    {
        $actual = $this->registry->addDefinition('my-ns', 'my-var', 42);

        self::assertInstanceOf(VarReference::class, $actual);
        self::assertSame('my-ns', $actual->getNamespace());
        self::assertSame('my-var', $actual->getName());
        self::assertSame('my-ns/my-var', $actual->getFullName());
    }

    public function test_has_namespace_false_when_missing(): void
    {
        self::assertFalse($this->registry->hasNamespace('missing-ns'));
    }

    public function test_has_namespace_true_after_add_definition(): void
    {
        $this->registry->addDefinition('ns-a', 'x', 1);

        self::assertTrue($this->registry->hasNamespace('ns-a'));
    }

    public function test_register_namespace_creates_empty_namespace(): void
    {
        $this->registry->registerNamespace('empty-ns');

        self::assertTrue($this->registry->hasNamespace('empty-ns'));
        self::assertSame([], $this->registry->getDefinitionInNamespace('empty-ns'));
    }

    public function test_register_namespace_is_idempotent(): void
    {
        $this->registry->addDefinition('ns-b', 'y', 2);
        $this->registry->registerNamespace('ns-b');

        self::assertSame(2, $this->registry->getDefinition('ns-b', 'y'));
    }

    public function test_remove_namespace_drops_definitions(): void
    {
        $this->registry->addDefinition('ns-c', 'z', 3);
        $this->registry->removeNamespace('ns-c');

        self::assertFalse($this->registry->hasNamespace('ns-c'));
        self::assertNull($this->registry->getDefinition('ns-c', 'z'));
    }

    public function test_remove_namespace_noop_when_missing(): void
    {
        $this->registry->removeNamespace('never-registered');

        self::assertFalse($this->registry->hasNamespace('never-registered'));
    }
}
