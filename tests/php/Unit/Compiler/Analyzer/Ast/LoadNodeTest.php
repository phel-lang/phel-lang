<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Ast\LoadNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Resolver\LoadPathResolution;
use Phel\Lang\SourceLocation;
use PHPUnit\Framework\TestCase;

final class LoadNodeTest extends TestCase
{
    public function test_creates_node_with_resolution_and_namespace(): void
    {
        $resolution = LoadPathResolution::filesystem('/app/util.phel');
        $node = new LoadNode($resolution, 'app\\main');

        self::assertSame($resolution, $node->getResolution());
        self::assertSame('app\\main', $node->getCallerNamespace());
    }

    public function test_stores_classpath_absolute_resolution(): void
    {
        $resolution = LoadPathResolution::classpathAbsolute('app/util.phel');
        $node = new LoadNode($resolution, 'app\\module');

        self::assertTrue($node->getResolution()->isClasspathAbsolute());
        self::assertSame('app/util.phel', $node->getResolution()->path);
    }

    public function test_has_empty_environment(): void
    {
        $node = new LoadNode(
            LoadPathResolution::filesystem('/any.phel'),
            'test\\ns',
        );

        self::assertEquals(NodeEnvironment::empty(), $node->getEnv());
    }

    public function test_preserves_source_location(): void
    {
        $sourceLocation = new SourceLocation('test.phel', 1, 0);
        $node = new LoadNode(
            LoadPathResolution::filesystem('/util.phel'),
            'app\\main',
            $sourceLocation,
        );

        self::assertSame($sourceLocation, $node->getStartSourceLocation());
    }

    public function test_source_location_is_optional(): void
    {
        $node = new LoadNode(
            LoadPathResolution::filesystem('/util.phel'),
            'app\\main',
        );

        self::assertNull($node->getStartSourceLocation());
    }
}
