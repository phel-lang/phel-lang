<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Domain\Compile;

use Phel\Build\Domain\Compile\CompiledTargetPathResolver;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\NamespaceInformation;
use PHPUnit\Framework\TestCase;

use function str_replace;

final class CompiledTargetPathResolverTest extends TestCase
{
    public function test_a_primary_is_placed_by_its_namespace(): void
    {
        self::assertSame(
            'app/main.php',
            $this->resolver()->resolve(new NamespaceInformation('/p/src/main.phel', 'app.main', [], true), ['/p/src']),
        );
    }

    public function test_a_secondary_sits_where_its_primary_looks_for_it_in_a_flat_layout(): void
    {
        // `(load "main_extra")` in src/main.phel: the built primary is out/app/main.php
        // and probes `__DIR__ . '/main_extra.php'`.
        self::assertSame(
            'app/main_extra.php',
            $this->resolver()->resolve(
                new NamespaceInformation('/p/src/main_extra.phel', 'app.main', [], false),
                ['/p/src'],
                '/p/src/main.phel',
            ),
        );
    }

    public function test_a_secondary_in_a_subdirectory_of_a_prefix_rooted_primary(): void
    {
        // phel-sql: src/sql.phel is `phel.sql` and loads "sql/util".
        self::assertSame(
            'phel/sql/util.php',
            $this->resolver()->resolve(
                new NamespaceInformation('/p/src/sql/util.phel', 'phel.sql', [], false),
                ['/p/src'],
                '/p/src/sql.phel',
            ),
        );
    }

    public function test_a_nested_layout_secondary_keeps_its_place(): void
    {
        self::assertSame(
            'phel/core/util.php',
            $this->resolver()->resolve(
                new NamespaceInformation('/p/src/phel/core/util.phel', 'phel.core', [], false),
                ['/p/src'],
                '/p/src/phel/core.phel',
            ),
        );
    }

    public function test_a_secondary_outside_its_primary_directory_falls_back_to_its_source_path(): void
    {
        self::assertSame(
            'shared/extra.php',
            $this->resolver()->resolve(
                new NamespaceInformation('/p/src/shared/extra.phel', 'app.main', [], false),
                ['/p/src'],
                '/p/src/app/main.phel',
            ),
        );
    }

    public function test_a_secondary_without_a_known_primary_falls_back_to_its_source_path(): void
    {
        self::assertSame(
            'main_extra.php',
            $this->resolver()->resolve(new NamespaceInformation('/p/src/main_extra.phel', 'app.main', [], false), ['/p/src']),
        );
    }

    private function resolver(): CompiledTargetPathResolver
    {
        $compilerFacade = $this->createStub(CompilerFacadeInterface::class);
        $compilerFacade->method('encodeNs')->willReturnCallback(
            static fn(string $ns): string => str_replace(['.', '-'], ['\\', '_'], $ns),
        );

        return new CompiledTargetPathResolver($compilerFacade);
    }
}
