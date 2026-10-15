<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Domain;

use Phel\Api\Domain\RegistryNamespace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RegistryNamespaceTest extends TestCase
{
    public static function registryNamespaces(): iterable
    {
        yield 'stdlib with a dash' => ['phel.http_client', 'phel.http-client'];
        yield 'stdlib in backslash form' => ['phel\\http_client', 'phel.http-client'];
        yield 'stdlib without a dash' => ['phel.string', 'phel.string'];
        yield 'user namespace written with an underscore' => ['my_app.core', 'my_app.core'];
        yield 'user namespace in backslash form' => ['my_app\\core', 'my_app.core'];
    }

    #[DataProvider('registryNamespaces')]
    public function test_it_names_a_registry_namespace_by_its_source_name(string $registryNs, string $expected): void
    {
        self::assertSame($expected, RegistryNamespace::sourceName($registryNs));
    }
}
