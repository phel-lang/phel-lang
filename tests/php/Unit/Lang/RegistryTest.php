<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Phel;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase
{
    public static function tearDownAfterClass(): void
    {
        Phel::clear();
    }

    protected function setUp(): void
    {
        Phel::clear();
    }

    public function test_null_when_non_existing_definition_by_value(): void
    {
        $actual = Phel::getDefinition('ns', 'non-existing');

        self::assertNull($actual);
    }

    public function test_value_definition(): void
    {
        Phel::addDefinition('ns', 'array', [1, 2, 3]);
        Phel::getDefinition('ns', 'array')[] = 4;
        $actual = Phel::getDefinition('ns', 'array');

        self::assertSame([1, 2, 3], $actual);
    }

    public function test_error_when_non_existing_definition_by_reference(): void
    {
        $this->expectExceptionMessage('Only variables can be returned by reference');

        Phel::getDefinitionReference('ns', 'non-existing');
    }

    public function test_reference_definition(): void
    {
        Phel::addDefinition('ns', 'array', [1, 2, 3]);
        Phel::getDefinitionReference('ns', 'array')[] = 4;
        $actual = Phel::getDefinition('ns', 'array');

        self::assertSame([1, 2, 3, 4], $actual);
    }
}
