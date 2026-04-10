<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\NsNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\NsSymbol;
use Phel\Lang\Keyword;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NsSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    private GlobalEnvironment $globalEnv;

    protected function setUp(): void
    {
        Registry::getInstance()->clear();
        $this->globalEnv = new GlobalEnvironment();
        $this->analyzer = new Analyzer($this->globalEnv);

        // Seed the Registry so clojure\* → phel\* remapping finds the target namespace
        Phel::addDefinition('phel\\test', '__ns_marker', true, Phel::map());
    }

    public function test_first_argument_must_be_symbol(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("First argument of 'ns must be a Symbol, got string");

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            'not-a-symbol',
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_invalid_namespace(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Invalid namespace.');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('1invalid'),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    #[DataProvider('phpKeywordNamespacePartProvider')]
    public function test_namespace_part_allows_php_keywords(string $keyword): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('foo\\' . $keyword),
        ]);

        $node = (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertSame('foo\\' . $keyword, $node->getNamespace());
    }

    public static function phpKeywordNamespacePartProvider(): iterable
    {
        foreach (['and', 'or', 'list', 'class', 'for', 'if', 'function'] as $keyword) {
            yield $keyword => [$keyword];
        }
    }

    #[DataProvider('invalidNamespacePartProvider')]
    public function test_namespace_part_with_invalid_characters_is_rejected(string $keyword): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessageMatches('/^Invalid namespace\./');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('foo\\' . $keyword),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public static function invalidNamespacePartProvider(): iterable
    {
        yield 'leading digit' => ['1invalid'];
        yield 'contains space' => ['foo bar'];
        yield 'contains at sign' => ['foo@bar'];
        yield 'trailing backslash' => ['bar\\'];
        yield 'empty part' => [''];
    }

    public function test_import_must_be_list(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("Import in 'ns must be Lists.");

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('foo\\bar'),
            'not-a-list',
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_use_first_argument_must_be_symbol(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('First argument in :use must be a symbol.');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('foo\\bar'),
            Phel::list([
                Keyword::create('use'),
                'not-a-symbol',
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_use_alias_must_be_symbol(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Alias must be a Symbol, got string');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('foo\\bar'),
            Phel::list([
                Keyword::create('use'),
                Symbol::create('Vendor\\Library'),
                Keyword::create('as'),
                'not-a-symbol',
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_require_first_argument_must_be_symbol(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('First argument in :require must be a symbol or vector.');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('foo\\bar'),
            Phel::list([
                Keyword::create('require'),
                'not-a-symbol',
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_require_accepts_single_vector_entry(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app\\core'),
            Phel::list([
                Keyword::create('require'),
                Phel::vector([
                    Symbol::create('vendor\\package'),
                ]),
            ]),
        ]);

        $nsNode = (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertEquals([
            Symbol::create('phel\\core'),
            Symbol::create('vendor\\package'),
        ], $nsNode->getRequireNs());
        self::assertTrue($this->globalEnv->hasRequireAlias('app\\core', Symbol::create('package')));
        self::assertSame('vendor\\package', $this->globalEnv->resolveAlias('package'));
    }

    public function test_require_vector_with_as_alias(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app\\core'),
            Phel::list([
                Keyword::create('require'),
                Phel::vector([
                    Symbol::create('vendor\\package'),
                    Keyword::create('as'),
                    Symbol::create('vp'),
                ]),
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertTrue($this->globalEnv->hasRequireAlias('app\\core', Symbol::create('vp')));
        self::assertSame('vendor\\package', $this->globalEnv->resolveAlias('vp'));
    }

    public function test_require_vector_with_refer(): void
    {
        Phel::addDefinition('vendor\\package', 'foo', 'fooValue', Phel::map());
        Phel::addDefinition('vendor\\package', 'bar', 'barValue', Phel::map());

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app\\core'),
            Phel::list([
                Keyword::create('require'),
                Phel::vector([
                    Symbol::create('vendor\\package'),
                    Keyword::create('refer'),
                    Phel::vector([
                        Symbol::create('foo'),
                        Symbol::create('bar'),
                    ]),
                ]),
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        $fooNode = $this->globalEnv->resolve(Symbol::create('foo'), NodeEnvironment::empty());
        self::assertInstanceOf(GlobalVarNode::class, $fooNode);
        self::assertSame('vendor\\package', $fooNode->getNamespace());

        $barNode = $this->globalEnv->resolve(Symbol::create('bar'), NodeEnvironment::empty());
        self::assertInstanceOf(GlobalVarNode::class, $barNode);
        self::assertSame('vendor\\package', $barNode->getNamespace());
    }

    public function test_require_vector_with_as_and_refer(): void
    {
        Phel::addDefinition('vendor\\package', 'baz', 'bazValue', Phel::map());

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app\\core'),
            Phel::list([
                Keyword::create('require'),
                Phel::vector([
                    Symbol::create('vendor\\package'),
                    Keyword::create('as'),
                    Symbol::create('vp'),
                    Keyword::create('refer'),
                    Phel::vector([
                        Symbol::create('baz'),
                    ]),
                ]),
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertTrue($this->globalEnv->hasRequireAlias('app\\core', Symbol::create('vp')));
        self::assertSame('vendor\\package', $this->globalEnv->resolveAlias('vp'));

        $bazNode = $this->globalEnv->resolve(Symbol::create('baz'), NodeEnvironment::empty());
        self::assertInstanceOf(GlobalVarNode::class, $bazNode);
        self::assertSame('vendor\\package', $bazNode->getNamespace());
    }

    public function test_require_multiple_vector_entries_in_one_clause(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app\\core'),
            Phel::list([
                Keyword::create('require'),
                Phel::vector([
                    Symbol::create('lib\\one'),
                    Keyword::create('as'),
                    Symbol::create('one'),
                ]),
                Phel::vector([
                    Symbol::create('lib\\two'),
                    Keyword::create('as'),
                    Symbol::create('two'),
                ]),
            ]),
        ]);

        $nsNode = (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertEquals([
            Symbol::create('phel\\core'),
            Symbol::create('lib\\one'),
            Symbol::create('lib\\two'),
        ], $nsNode->getRequireNs());
        self::assertTrue($this->globalEnv->hasRequireAlias('app\\core', Symbol::create('one')));
        self::assertTrue($this->globalEnv->hasRequireAlias('app\\core', Symbol::create('two')));
        self::assertSame('lib\\one', $this->globalEnv->resolveAlias('one'));
        self::assertSame('lib\\two', $this->globalEnv->resolveAlias('two'));
    }

    public function test_require_mixed_vector_and_flat_entries_in_one_clause(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app\\core'),
            Phel::list([
                Keyword::create('require'),
                Phel::vector([
                    Symbol::create('lib\\one'),
                    Keyword::create('as'),
                    Symbol::create('one'),
                ]),
                Symbol::create('lib\\two'),
                Keyword::create('as'),
                Symbol::create('two'),
            ]),
        ]);

        $nsNode = (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertEquals([
            Symbol::create('phel\\core'),
            Symbol::create('lib\\one'),
            Symbol::create('lib\\two'),
        ], $nsNode->getRequireNs());
        self::assertTrue($this->globalEnv->hasRequireAlias('app\\core', Symbol::create('one')));
        self::assertTrue($this->globalEnv->hasRequireAlias('app\\core', Symbol::create('two')));
    }

    public function test_require_vector_with_dot_separator_normalized(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app\\core'),
            Phel::list([
                Keyword::create('require'),
                Phel::vector([
                    Symbol::create('my.cljc.file'),
                    Keyword::create('as'),
                    Symbol::create('mcf'),
                ]),
            ]),
        ]);

        $nsNode = (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertEquals([
            Symbol::create('phel\\core'),
            Symbol::create('my\\cljc\\file'),
        ], $nsNode->getRequireNs());
        self::assertTrue($this->globalEnv->hasRequireAlias('app\\core', Symbol::create('mcf')));
        self::assertSame('my\\cljc\\file', $this->globalEnv->resolveAlias('mcf'));
    }

    public function test_require_vector_first_element_must_be_symbol(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('First element of :require vector must be a symbol.');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('foo\\bar'),
            Phel::list([
                Keyword::create('require'),
                Phel::vector([
                    Keyword::create('not-a-symbol'),
                ]),
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_require_alias_must_be_symbol(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Alias must be a Symbol, got string');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('foo\\bar'),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('vendor\\package'),
                Keyword::create('as'),
                'not-a-symbol',
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_require_refer_must_be_vector(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Refer must be a vector');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('foo\\bar'),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('vendor\\package'),
                Keyword::create('refer'),
                Phel::list([]),
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_require_refer_elements_must_be_symbol(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Each refer element must be a Symbol, got string');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('foo\\bar'),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('vendor\\package'),
                Keyword::create('refer'),
                Phel::vector(['not-a-symbol']),
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_require_file_first_argument_must_be_string(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('First argument in :require-file must be a string.');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('foo\\bar'),
            Phel::list([
                Keyword::create('require-file'),
                123,
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_dot_separator_in_namespace_is_normalized_to_backslash(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('my.cljc.file'),
        ]);

        $nsNode = (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertSame('my\\cljc\\file', $nsNode->getNamespace());
        self::assertSame('my\\cljc\\file', $this->analyzer->getNamespace());
    }

    public function test_dot_separator_in_require_is_normalized_to_backslash(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app.core'),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('vendor.package'),
            ]),
        ]);

        $nsNode = (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertSame('app\\core', $nsNode->getNamespace());
        self::assertEquals([
            Symbol::create('phel\\core'),
            Symbol::create('vendor\\package'),
        ], $nsNode->getRequireNs());
        self::assertTrue($this->globalEnv->hasRequireAlias('app\\core', Symbol::create('package')));
        self::assertSame('vendor\\package', $this->globalEnv->resolveAlias('package'));
    }

    public function test_dot_separator_in_use_is_normalized_to_backslash(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app.core'),
            Phel::list([
                Keyword::create('use'),
                Symbol::create('Vendor.Library'),
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertTrue($this->globalEnv->hasUseAlias('app\\core', Symbol::create('Library')));

        $phpClassNode = $this->globalEnv->resolve(Symbol::create('Library'), NodeEnvironment::empty());
        self::assertInstanceOf(PhpClassNameNode::class, $phpClassNode);
        self::assertSame('\\Vendor\\Library', $phpClassNode->getName()->getName());
    }

    public function test_mixed_separators_are_normalized(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('my.foo\\bar'),
        ]);

        $nsNode = (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertSame('my\\foo\\bar', $nsNode->getNamespace());
    }

    public function test_dot_namespace_with_empty_part_is_still_rejected(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Invalid namespace.');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('my..bar'),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_dot_namespace_with_php_keyword_part_is_allowed(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('my.list'),
        ]);

        $node = (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertSame('my\\list', $node->getNamespace());
    }

    public function test_dot_namespace_with_invalid_character_part_is_still_rejected(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Invalid namespace.');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('my.foo@bar'),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_dot_separator_in_require_auto_derives_last_segment_alias(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app.core'),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('my.cljc.file'),
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        // Auto-derived alias should be the last segment after normalization
        self::assertTrue($this->globalEnv->hasRequireAlias('app\\core', Symbol::create('file')));
        self::assertSame('my\\cljc\\file', $this->globalEnv->resolveAlias('file'));
    }

    public function test_dot_separator_in_require_with_explicit_as_alias(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app.core'),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('my.cljc.file'),
                Keyword::create('as'),
                Symbol::create('mcf'),
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertTrue($this->globalEnv->hasRequireAlias('app\\core', Symbol::create('mcf')));
        self::assertSame('my\\cljc\\file', $this->globalEnv->resolveAlias('mcf'));
    }

    public function test_dot_separator_in_require_with_refer(): void
    {
        Phel::addDefinition('vendor\\package', 'foo', 'fooValue', Phel::map());
        Phel::addDefinition('vendor\\package', 'bar', 'barValue', Phel::map());

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app.core'),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('vendor.package'),
                Keyword::create('refer'),
                Phel::vector([
                    Symbol::create('foo'),
                    Symbol::create('bar'),
                ]),
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        // Both refers should resolve through the normalized vendor namespace
        $fooNode = $this->globalEnv->resolve(Symbol::create('foo'), NodeEnvironment::empty());
        self::assertInstanceOf(GlobalVarNode::class, $fooNode);
        self::assertSame('vendor\\package', $fooNode->getNamespace());

        $barNode = $this->globalEnv->resolve(Symbol::create('bar'), NodeEnvironment::empty());
        self::assertInstanceOf(GlobalVarNode::class, $barNode);
        self::assertSame('vendor\\package', $barNode->getNamespace());
    }

    public function test_dot_separator_in_require_with_both_as_and_refer(): void
    {
        Phel::addDefinition('vendor\\package', 'baz', 'bazValue', Phel::map());

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app.core'),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('vendor.package'),
                Keyword::create('as'),
                Symbol::create('vp'),
                Keyword::create('refer'),
                Phel::vector([
                    Symbol::create('baz'),
                ]),
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertTrue($this->globalEnv->hasRequireAlias('app\\core', Symbol::create('vp')));
        self::assertSame('vendor\\package', $this->globalEnv->resolveAlias('vp'));

        $bazNode = $this->globalEnv->resolve(Symbol::create('baz'), NodeEnvironment::empty());
        self::assertInstanceOf(GlobalVarNode::class, $bazNode);
        self::assertSame('vendor\\package', $bazNode->getNamespace());
    }

    public function test_dot_separator_in_multiple_require_symbols_same_clause(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app.core'),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('lib.one'),
                Symbol::create('lib.two'),
            ]),
        ]);

        $nsNode = (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertEquals([
            Symbol::create('phel\\core'),
            Symbol::create('lib\\one'),
            Symbol::create('lib\\two'),
        ], $nsNode->getRequireNs());
        self::assertTrue($this->globalEnv->hasRequireAlias('app\\core', Symbol::create('one')));
        self::assertTrue($this->globalEnv->hasRequireAlias('app\\core', Symbol::create('two')));
    }

    public function test_dot_separator_in_multiple_require_clauses(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app.core'),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('first.lib'),
            ]),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('second.lib'),
            ]),
        ]);

        $nsNode = (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertEquals([
            Symbol::create('phel\\core'),
            Symbol::create('first\\lib'),
            Symbol::create('second\\lib'),
        ], $nsNode->getRequireNs());
    }

    public function test_dot_separator_in_use_with_multiple_symbols(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app.core'),
            Phel::list([
                Keyword::create('use'),
                Symbol::create('Vendor.Library'),
                Symbol::create('Vendor.Toolkit'),
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertTrue($this->globalEnv->hasUseAlias('app\\core', Symbol::create('Library')));
        self::assertTrue($this->globalEnv->hasUseAlias('app\\core', Symbol::create('Toolkit')));

        $libraryNode = $this->globalEnv->resolve(Symbol::create('Library'), NodeEnvironment::empty());
        self::assertInstanceOf(PhpClassNameNode::class, $libraryNode);
        self::assertSame('\\Vendor\\Library', $libraryNode->getName()->getName());

        $toolkitNode = $this->globalEnv->resolve(Symbol::create('Toolkit'), NodeEnvironment::empty());
        self::assertInstanceOf(PhpClassNameNode::class, $toolkitNode);
        self::assertSame('\\Vendor\\Toolkit', $toolkitNode->getName()->getName());
    }

    public function test_dot_separator_in_use_with_explicit_as_alias(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app.core'),
            Phel::list([
                Keyword::create('use'),
                Symbol::create('Vendor.Toolkit'),
                Keyword::create('as'),
                Symbol::create('Kit'),
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertTrue($this->globalEnv->hasUseAlias('app\\core', Symbol::create('Kit')));

        $kitNode = $this->globalEnv->resolve(Symbol::create('Kit'), NodeEnvironment::empty());
        self::assertInstanceOf(PhpClassNameNode::class, $kitNode);
        self::assertSame('\\Vendor\\Toolkit', $kitNode->getName()->getName());
    }

    public function test_clojure_namespace_remapped_to_phel_in_require(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app\\core'),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('clojure\\test'),
            ]),
        ]);

        $nsNode = (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertEquals([
            Symbol::create('phel\\core'),
            Symbol::create('phel\\test'),
        ], $nsNode->getRequireNs());
    }

    public function test_clojure_namespace_auto_alias_points_to_phel(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app\\core'),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('clojure\\test'),
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertTrue($this->globalEnv->hasRequireAlias('app\\core', Symbol::create('test')));
        self::assertSame('phel\\test', $this->globalEnv->resolveAlias('test'));
    }

    public function test_clojure_namespace_with_explicit_as_alias(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app\\core'),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('clojure\\test'),
                Keyword::create('as'),
                Symbol::create('t'),
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertTrue($this->globalEnv->hasRequireAlias('app\\core', Symbol::create('t')));
        self::assertSame('phel\\test', $this->globalEnv->resolveAlias('t'));
    }

    public function test_clojure_namespace_with_refer(): void
    {
        Phel::addDefinition('phel\\test', 'deftest', 'value', Phel::map());

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app\\core'),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('clojure\\test'),
                Keyword::create('refer'),
                Phel::vector([
                    Symbol::create('deftest'),
                ]),
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        $node = $this->globalEnv->resolve(Symbol::create('deftest'), NodeEnvironment::empty());
        self::assertInstanceOf(GlobalVarNode::class, $node);
        self::assertSame('phel\\test', $node->getNamespace());
    }

    public function test_clojure_namespace_original_registered_as_alias(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app\\core'),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('clojure\\test'),
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertTrue($this->globalEnv->hasRequireAlias('app\\core', Symbol::create('clojure\\test')));
        self::assertSame('phel\\test', $this->globalEnv->resolveAlias('clojure\\test'));
    }

    public function test_clojure_namespace_via_dot_syntax(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app.core'),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('clojure.test'),
            ]),
        ]);

        $nsNode = (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertEquals([
            Symbol::create('phel\\core'),
            Symbol::create('phel\\test'),
        ], $nsNode->getRequireNs());
        self::assertTrue($this->globalEnv->hasRequireAlias('app\\core', Symbol::create('test')));
        self::assertSame('phel\\test', $this->globalEnv->resolveAlias('test'));
    }

    public function test_clojure_namespace_vector_form_with_as_and_refer(): void
    {
        Phel::addDefinition('phel\\test', 'is', 'value', Phel::map());

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app.core'),
            Phel::list([
                Keyword::create('require'),
                Phel::vector([
                    Symbol::create('clojure.test'),
                    Keyword::create('as'),
                    Symbol::create('t'),
                    Keyword::create('refer'),
                    Phel::vector([
                        Symbol::create('is'),
                    ]),
                ]),
            ]),
        ]);

        $nsNode = (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertEquals([
            Symbol::create('phel\\core'),
            Symbol::create('phel\\test'),
        ], $nsNode->getRequireNs());
        self::assertTrue($this->globalEnv->hasRequireAlias('app\\core', Symbol::create('t')));
        self::assertSame('phel\\test', $this->globalEnv->resolveAlias('t'));

        $isNode = $this->globalEnv->resolve(Symbol::create('is'), NodeEnvironment::empty());
        self::assertInstanceOf(GlobalVarNode::class, $isNode);
        self::assertSame('phel\\test', $isNode->getNamespace());
    }

    public function test_clojure_namespace_not_remapped_when_phel_equivalent_missing(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app\\core'),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('clojure\\core-test\\portability'),
            ]),
        ]);

        $nsNode = (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertEquals([
            Symbol::create('phel\\core'),
            Symbol::create('clojure\\core-test\\portability'),
        ], $nsNode->getRequireNs());
        self::assertSame('clojure\\core-test\\portability', $this->globalEnv->resolveAlias('portability'));
    }

    public function test_clojure_namespace_not_remapped_via_dot_syntax_when_phel_equivalent_missing(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app.core'),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('clojure.core-test.portability'),
            ]),
        ]);

        $nsNode = (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertEquals([
            Symbol::create('phel\\core'),
            Symbol::create('clojure\\core-test\\portability'),
        ], $nsNode->getRequireNs());
        self::assertSame('clojure\\core-test\\portability', $this->globalEnv->resolveAlias('portability'));
    }

    public function test_clojure_namespace_not_remapped_in_vector_form_when_phel_equivalent_missing(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app.core'),
            Phel::list([
                Keyword::create('require'),
                Phel::vector([
                    Symbol::create('clojure.core-test.portability'),
                    Keyword::create('as'),
                    Symbol::create('p'),
                ]),
            ]),
        ]);

        $nsNode = (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertEquals([
            Symbol::create('phel\\core'),
            Symbol::create('clojure\\core-test\\portability'),
        ], $nsNode->getRequireNs());
        self::assertTrue($this->globalEnv->hasRequireAlias('app\\core', Symbol::create('p')));
        self::assertSame('clojure\\core-test\\portability', $this->globalEnv->resolveAlias('p'));
    }

    public function test_clojure_namespace_no_spurious_original_alias_when_not_remapped(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app\\core'),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('clojure\\custom\\lib'),
            ]),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        // When no remap happens, resolvedSymbol === requireSymbol,
        // so the original clojure\* name should NOT be registered as a separate alias
        self::assertTrue($this->globalEnv->hasRequireAlias('app\\core', Symbol::create('lib')));
        self::assertFalse($this->globalEnv->hasRequireAlias('app\\core', Symbol::create('clojure\\custom\\lib')));
    }

    public function test_clojure_remap_uses_munged_namespace_for_registry_lookup(): void
    {
        // Register with munged name (dashes → underscores), matching how the compiler stores it
        Phel::addDefinition('phel\\my_lib', '__ns_marker', true, Phel::map());

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app\\core'),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('clojure\\my-lib'),
            ]),
        ]);

        $nsNode = (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        // Should remap because phel\my_lib exists (munged form of phel\my-lib)
        self::assertEquals([
            Symbol::create('phel\\core'),
            Symbol::create('phel\\my-lib'),
        ], $nsNode->getRequireNs());
    }

    public function test_non_clojure_namespace_not_remapped(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('app\\core'),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('vendor\\package'),
            ]),
        ]);

        $nsNode = (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertEquals([
            Symbol::create('phel\\core'),
            Symbol::create('vendor\\package'),
        ], $nsNode->getRequireNs());
        self::assertSame('vendor\\package', $this->globalEnv->resolveAlias('package'));
    }

    public function test_backslash_only_namespaces_still_work_unchanged(): void
    {
        // Regression: confirm the canonical \\-form still produces identical output
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('vendor\\package'),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('other\\lib'),
                Keyword::create('as'),
                Symbol::create('ol'),
            ]),
        ]);

        $nsNode = (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertSame('vendor\\package', $nsNode->getNamespace());
        self::assertEquals([
            Symbol::create('phel\\core'),
            Symbol::create('other\\lib'),
        ], $nsNode->getRequireNs());
        self::assertTrue($this->globalEnv->hasRequireAlias('vendor\\package', Symbol::create('ol')));
    }

    public function test_it_sets_namespace_and_registers_imports(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('my\\project'),
            Phel::list([
                Keyword::create('use'),
                Symbol::create('Vendor\\Library'),
                Symbol::create('Vendor\\Toolkit'),
                Keyword::create('as'),
                Symbol::create('Kit'),
            ]),
            Phel::list([
                Keyword::create('require'),
                Symbol::create('vendor\\package'),
                Keyword::create('refer'),
                Phel::vector([
                    Symbol::create('foo'),
                ]),
            ]),
            Phel::list([
                Keyword::create('require-file'),
                'src/config.phel',
            ]),
        ]);

        $nsNode = (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertInstanceOf(NsNode::class, $nsNode);
        self::assertSame('my\\project', $nsNode->getNamespace());
        self::assertEquals([
            Symbol::create('phel\\core'),
            Symbol::create('vendor\\package'),
        ], $nsNode->getRequireNs());
        self::assertSame(['src/config.phel'], $nsNode->getRequireFiles());
        self::assertSame('my\\project', $this->analyzer->getNamespace());
        self::assertTrue($this->globalEnv->hasUseAlias('my\\project', Symbol::create('Library')));
        self::assertTrue($this->globalEnv->hasUseAlias('my\\project', Symbol::create('Kit')));
        self::assertTrue($this->globalEnv->hasRequireAlias('my\\project', Symbol::create('package')));
        self::assertSame('vendor\\package', $this->globalEnv->resolveAlias('package'));

        $phpClassNode = $this->globalEnv->resolve(Symbol::create('Library'), NodeEnvironment::empty());
        self::assertInstanceOf(PhpClassNameNode::class, $phpClassNode);
        self::assertSame('\\Vendor\\Library', $phpClassNode->getName()->getName());

        $phpClassNodeAlias = $this->globalEnv->resolve(Symbol::create('Kit'), NodeEnvironment::empty());
        self::assertInstanceOf(PhpClassNameNode::class, $phpClassNodeAlias);
        self::assertSame('\\Vendor\\Toolkit', $phpClassNodeAlias->getName()->getName());

        Phel::addDefinition('vendor\\package', 'foo', 'value', Phel::map());
        $globalVarNode = $this->globalEnv->resolve(Symbol::create('foo'), NodeEnvironment::empty());
        self::assertInstanceOf(GlobalVarNode::class, $globalVarNode);
        self::assertSame('vendor\\package', $globalVarNode->getNamespace());
    }
}
