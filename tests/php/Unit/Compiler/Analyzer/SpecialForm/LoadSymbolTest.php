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
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LoadSymbolTest extends TestCase
{
    private const string CALLER_FILE = '/fixtures/app/main.phel';

    private Analyzer $analyzer;

    protected function setUp(): void
    {
        Registry::getInstance()->clear();
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function test_requires_at_least_one_argument(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("'load requires exactly 1 argument (the file path)");

        $this->analyze($this->makeList([]));
    }

    public function test_requires_at_most_one_argument(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("'load requires exactly 1 argument, got 2");

        $this->analyze($this->makeList(['util', 'helper']));
    }

    #[DataProvider('providerNonStringArguments')]
    public function test_rejects_non_string_arguments(mixed $invalidArg, string $expectedType): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("First argument of 'load must be a string, got: " . $expectedType);

        $this->analyze($this->makeList([$invalidArg]));
    }

    public static function providerNonStringArguments(): Generator
    {
        yield 'Integer' => [123, 'int'];
        yield 'Float' => [3.14, 'float'];
        yield 'Boolean' => [true, 'bool'];
        yield 'Null' => [null, 'null'];
        yield 'Symbol' => [Symbol::create('path'), Symbol::class];
        yield 'Array' => [[], 'array'];
    }

    public function test_resolves_relative_path_against_caller_namespace(): void
    {
        $this->analyzer->setNamespace('app\\main');
        $node = $this->analyze($this->makeList(['util']));

        self::assertInstanceOf(LoadNode::class, $node);
        self::assertFalse($node->getResolution()->isClasspathAbsolute());
        self::assertSame('util', $node->getResolution()->loadKey);
        self::assertSame('app', $node->getResolution()->callerClasspathDir);
        self::assertSame('app\\main', $node->getCallerNamespace());
    }

    public function test_resolves_nested_relative_path(): void
    {
        $this->analyzer->setNamespace('app\\main');
        $node = $this->analyze($this->makeList(['sub/util']));

        self::assertSame('sub/util', $node->getResolution()->loadKey);
        self::assertSame('app', $node->getResolution()->callerClasspathDir);
    }

    public function test_resolves_classpath_absolute_path(): void
    {
        $this->analyzer->setNamespace('app\\main');
        $node = $this->analyze($this->makeList(['/phel/str']));

        self::assertTrue($node->getResolution()->isClasspathAbsolute());
        self::assertSame('phel/str', $node->getResolution()->loadKey);
        self::assertSame('', $node->getResolution()->callerClasspathDir);
    }

    public function test_preserves_source_location(): void
    {
        $this->analyzer->setNamespace('test\\ns');
        $list = $this->makeList(['util']);

        $node = $this->analyze($list);

        self::assertSame($list->getStartLocation(), $node->getStartSourceLocation());
    }

    #[DataProvider('providerRejectedPaths')]
    public function test_rejects_invalid_path_arguments(string $pathArg, string $expectedMessage): void
    {
        $this->analyzer->setNamespace('test\\ns');

        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->analyze($this->makeList([$pathArg]));
    }

    public static function providerRejectedPaths(): Generator
    {
        yield 'empty path'            => ['',           'must not be empty'];
        yield 'explicit extension'    => ['util.phel',  'must not include'];
        yield 'dot-slash prefix'      => ['./util',     "must not start with './'"];
        yield 'parent-slash prefix'   => ['../util',    "must not start with './'"];
    }

    /**
     * @param list<mixed> $args
     */
    private function makeList(array $args): PersistentListInterface
    {
        $list = Phel::list([Symbol::create(Symbol::NAME_LOAD), ...$args]);
        $list->setStartLocation(new SourceLocation(self::CALLER_FILE, 1, 0));

        return $list;
    }

    private function analyze(PersistentListInterface $list): LoadNode
    {
        return (new LoadSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }
}
