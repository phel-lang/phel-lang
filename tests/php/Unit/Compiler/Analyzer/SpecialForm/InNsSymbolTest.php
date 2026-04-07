<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Generator;
use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\Ast\InNsNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\InNsSymbol;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use Phel\Shared\ReplConstants;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class InNsSymbolTest extends TestCase
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
        $this->expectExceptionMessage("'in-ns requires exactly 1 argument (the namespace)");

        $list = Phel::list([
            Symbol::create(Symbol::NAME_IN_NS),
            // No argument provided
        ]);

        $this->analyze($list);
    }

    public function test_requires_at_most_one_argument(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("'in-ns requires exactly 1 argument, got 2");

        $list = Phel::list([
            Symbol::create(Symbol::NAME_IN_NS),
            Symbol::create('ns1'),
            Symbol::create('ns2'), // Too many arguments
        ]);

        $this->analyze($list);
    }

    public function test_first_argument_must_be_symbol_or_string(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("First argument of 'in-ns must be a Symbol or String, got: int");

        $list = Phel::list([
            Symbol::create(Symbol::NAME_IN_NS),
            123, // Invalid - not a symbol or string
        ]);

        $this->analyze($list);
    }

    public function test_rejects_empty_namespace(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Namespace cannot be empty');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_IN_NS),
            '', // Empty string
        ]);

        $this->analyze($list);
    }

    public function test_rejects_whitespace_only_namespace(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Namespace cannot be empty');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_IN_NS),
            '   ', // Whitespace only
        ]);

        $this->analyze($list);
    }

    #[DataProvider('providerValidNamespaceArgs')]
    public function test_accepts_symbol_or_string(mixed $nsArg, string $expectedNs): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_IN_NS),
            $nsArg,
        ]);

        $node = $this->analyze($list);

        self::assertInstanceOf(InNsNode::class, $node);
        self::assertSame($expectedNs, $node->getNamespace());
        self::assertSame($expectedNs, $this->analyzer->getNamespace());
    }

    public static function providerValidNamespaceArgs(): Generator
    {
        yield 'Symbol namespace' => [
            Symbol::create('my\\namespace'),
            'my\\namespace',
        ];

        yield 'String namespace' => [
            'my\\namespace',
            'my\\namespace',
        ];

        yield 'Simple namespace' => [
            Symbol::create('simple'),
            'simple',
        ];

        yield 'Deep namespace' => [
            'app\\modules\\user\\domain',
            'app\\modules\\user\\domain',
        ];

        yield 'Dot-separated symbol' => [
            Symbol::create('my.cljc.file'),
            'my\\cljc\\file',
        ];

        yield 'Dot-separated string' => [
            'app.modules.user',
            'app\\modules\\user',
        ];

        yield 'Mixed separators' => [
            Symbol::create('my.foo\\bar'),
            'my\\foo\\bar',
        ];
    }

    public function test_sets_namespace_in_analyzer(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_IN_NS),
            Symbol::create('test\\namespace'),
        ]);

        // Initially should be empty or default
        self::assertNotSame('test\\namespace', $this->analyzer->getNamespace());

        $this->analyze($list);

        // After analyzing, namespace should be set
        self::assertSame('test\\namespace', $this->analyzer->getNamespace());
    }

    public function test_preserves_source_location(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_IN_NS),
            Symbol::create('my\\ns'),
        ]);

        $node = $this->analyze($list);

        self::assertSame($list->getStartLocation(), $node->getStartSourceLocation());
    }

    public function test_injects_repl_refers_when_repl_mode_active(): void
    {
        Phel::addDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, ReplConstants::REPL_MODE, true);

        $list = Phel::list([
            Symbol::create(Symbol::NAME_IN_NS),
            Symbol::create('my\\new-ns'),
        ]);

        $this->analyze($list);

        $globalEnv = $this->getGlobalEnvironment();

        $aliases = $globalEnv->getRequireAliases('my\\new-ns');
        self::assertArrayHasKey('repl', $aliases, 'repl alias should be registered after in-ns in REPL mode');
        self::assertSame('phel\\repl', $aliases['repl']->getName());

        $refers = $globalEnv->getRefers('my\\new-ns');
        self::assertArrayHasKey('doc', $refers, 'doc should be referred after in-ns in REPL mode');
        self::assertArrayHasKey('require', $refers, 'require should be referred after in-ns in REPL mode');
        self::assertArrayHasKey('use', $refers, 'use should be referred after in-ns in REPL mode');
    }

    public function test_does_not_inject_repl_refers_when_repl_mode_inactive(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_IN_NS),
            Symbol::create('my\\other-ns'),
        ]);

        $this->analyze($list);

        $globalEnv = $this->getGlobalEnvironment();

        $aliases = $globalEnv->getRequireAliases('my\\other-ns');
        self::assertArrayNotHasKey('repl', $aliases, 'repl alias should not exist when not in REPL mode');

        $refers = $globalEnv->getRefers('my\\other-ns');
        self::assertEmpty($refers, 'No refers should be injected when not in REPL mode');
    }

    private function analyze(PersistentListInterface $list): InNsNode
    {
        return (new InNsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    private function getGlobalEnvironment(): GlobalEnvironment
    {
        $ref = new ReflectionProperty(Analyzer::class, 'globalEnvironment');

        return $ref->getValue($this->analyzer);
    }
}
