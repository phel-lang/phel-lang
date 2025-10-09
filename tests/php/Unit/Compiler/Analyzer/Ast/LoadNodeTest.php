<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Ast\LoadNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Lang\SourceLocation;
use PHPUnit\Framework\TestCase;

final class LoadNodeTest extends TestCase
{
    public function test_creates_node_with_file_path_and_namespace(): void
    {
        $node = new LoadNode('./util.phel', 'app\\main');

        self::assertSame('./util.phel', $node->getFilePath());
        self::assertSame('app\\main', $node->getCallerNamespace());
    }

    public function test_stores_different_paths_and_namespaces(): void
    {
        $node1 = new LoadNode('./helper.phel', 'app\\module1');
        $node2 = new LoadNode('/abs/path/file.phel', 'app\\module2\\domain');

        self::assertSame('./helper.phel', $node1->getFilePath());
        self::assertSame('app\\module1', $node1->getCallerNamespace());

        self::assertSame('/abs/path/file.phel', $node2->getFilePath());
        self::assertSame('app\\module2\\domain', $node2->getCallerNamespace());
    }

    public function test_has_empty_environment(): void
    {
        $node = new LoadNode('./file.phel', 'test\\ns');

        self::assertEquals(NodeEnvironment::empty(), $node->getEnv());
    }

    public function test_preserves_source_location(): void
    {
        $sourceLocation = new SourceLocation('test.phel', 1, 0);
        $node = new LoadNode('./util.phel', 'app\\main', $sourceLocation);

        self::assertSame($sourceLocation, $node->getStartSourceLocation());
    }

    public function test_source_location_is_optional(): void
    {
        $node = new LoadNode('./util.phel', 'app\\main');

        self::assertNull($node->getStartSourceLocation());
    }

    public function test_handles_paths_without_extension(): void
    {
        $node = new LoadNode('./util', 'app\\main');

        self::assertSame('./util', $node->getFilePath());
    }

    public function test_handles_various_path_formats(): void
    {
        $testCases = [
            ['./relative.phel', 'test\\ns'],
            ['../parent/file.phel', 'test\\ns'],
            ['/absolute/path.phel', 'test\\ns'],
            ['simple.phel', 'test\\ns'],
            ['./deep/nested/path/file.phel', 'test\\ns'],
        ];

        foreach ($testCases as [$path, $ns]) {
            $node = new LoadNode($path, $ns);
            self::assertSame($path, $node->getFilePath());
            self::assertSame($ns, $node->getCallerNamespace());
        }
    }
}
