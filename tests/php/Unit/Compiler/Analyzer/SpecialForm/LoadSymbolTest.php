<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Generator;
use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\Ast\LoadNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\LoadSymbol;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LoadSymbolTest extends TestCase
{
    private Analyzer $analyzer;

    protected function setUp(): void
    {
        Registry::getInstance()->clear();
        $globalEnv = new GlobalEnvironment();
        $this->analyzer = new Analyzer($globalEnv);
    }

    public function test_requires_at_least_one_argument(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("'load requires exactly 1 argument (the file path)");

        $list = Phel::list([
            Symbol::create(Symbol::NAME_LOAD),
            // No argument provided
        ]);

        $this->analyze($list);
    }

    public function test_requires_at_most_one_argument(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("'load requires exactly 1 argument, got 2");

        $list = Phel::list([
            Symbol::create(Symbol::NAME_LOAD),
            './file1.phel',
            './file2.phel', // Too many arguments
        ]);

        $this->analyze($list);
    }

    public function test_first_argument_must_be_string(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("First argument of 'load must be a string, got: int");

        $list = Phel::list([
            Symbol::create(Symbol::NAME_LOAD),
            123, // Invalid - not a string
        ]);

        $this->analyze($list);
    }

    #[DataProvider('providerFirstArgumentMustBeString')]
    public function test_rejects_non_string_arguments(mixed $invalidArg, string $expectedType): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("First argument of 'load must be a string, got: " . $expectedType);

        $list = Phel::list([
            Symbol::create(Symbol::NAME_LOAD),
            $invalidArg,
        ]);

        $this->analyze($list);
    }

    public static function providerFirstArgumentMustBeString(): Generator
    {
        yield 'Integer' => [123, 'int'];
        yield 'Float' => [3.14, 'float'];
        yield 'Boolean' => [true, 'bool'];
        yield 'Null' => [null, 'null'];
        yield 'Symbol' => [Symbol::create('path'), Symbol::class];
        yield 'Array' => [[], 'array'];
    }

    #[DataProvider('providerValidFilePaths')]
    public function test_accepts_valid_file_paths(string $filePath): void
    {
        // Set a namespace first
        $this->analyzer->setNamespace('test\\namespace');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_LOAD),
            $filePath,
        ]);

        $node = $this->analyze($list);

        self::assertInstanceOf(LoadNode::class, $node);
        self::assertSame($filePath, $node->getFilePath());
        self::assertSame('test\\namespace', $node->getCallerNamespace());
    }

    public static function providerValidFilePaths(): Generator
    {
        yield 'Relative path with extension' => ['./util.phel'];
        yield 'Relative path without extension' => ['./util'];
        yield 'Absolute path' => ['/path/to/file.phel'];
        yield 'Parent directory' => ['../shared/helper.phel'];
        yield 'Deep relative path' => ['./lib/domain/user.phel'];
        yield 'Simple filename' => ['util.phel'];
    }

    public function test_captures_caller_namespace(): void
    {
        $this->analyzer->setNamespace('app\\main');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_LOAD),
            './util.phel',
        ]);

        $node = $this->analyze($list);

        self::assertSame('app\\main', $node->getCallerNamespace());
    }

    public function test_different_caller_namespaces(): void
    {
        // Test with first namespace
        $this->analyzer->setNamespace('app\\module1');
        $list1 = Phel::list([
            Symbol::create(Symbol::NAME_LOAD),
            './file1.phel',
        ]);
        $node1 = $this->analyze($list1);
        self::assertSame('app\\module1', $node1->getCallerNamespace());

        // Test with second namespace
        $this->analyzer->setNamespace('app\\module2');
        $list2 = Phel::list([
            Symbol::create(Symbol::NAME_LOAD),
            './file2.phel',
        ]);
        $node2 = $this->analyze($list2);
        self::assertSame('app\\module2', $node2->getCallerNamespace());
    }

    public function test_preserves_source_location(): void
    {
        $this->analyzer->setNamespace('test\\ns');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_LOAD),
            './file.phel',
        ]);

        $node = $this->analyze($list);

        self::assertSame($list->getStartLocation(), $node->getStartSourceLocation());
    }

    public function test_file_path_is_stored_as_provided(): void
    {
        $this->analyzer->setNamespace('test\\ns');

        // Path without extension
        $list1 = Phel::list([
            Symbol::create(Symbol::NAME_LOAD),
            './util',
        ]);
        $node1 = $this->analyze($list1);
        self::assertSame('./util', $node1->getFilePath());

        // Path with extension
        $list2 = Phel::list([
            Symbol::create(Symbol::NAME_LOAD),
            './util.phel',
        ]);
        $node2 = $this->analyze($list2);
        self::assertSame('./util.phel', $node2->getFilePath());
    }

    private function analyze(PersistentListInterface $list): LoadNode
    {
        return (new LoadSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }
}
