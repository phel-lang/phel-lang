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
    }

    public function test_first_argument_must_be_symbol(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("First argument of 'ns must be a Symbol");

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            'not-a-symbol',
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_invalid_namespace(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('The namespace is not valid.');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('1invalid'),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_namespace_part_cannot_be_php_keyword(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("The namespace is not valid. The part 'class' cannot be used because it is a reserved keyword.");

        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('foo\\class'),
        ]);

        (new NsSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
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
        $this->expectExceptionMessage('Alias must be a Symbol');

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
        $this->expectExceptionMessage('First argument in :require must be a symbol.');

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

    public function test_require_alias_must_be_symbol(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Alias must be a Symbol');

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
        $this->expectExceptionMessage('Each refer element must be a Symbol');

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

    public function test_it_sets_namespace_and_registers_imports(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_NS),
            Symbol::create('my\\project'),
            Phel::list([
                Keyword::create('use'),
                Symbol::create('Vendor\\Library'),
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
        self::assertTrue($this->globalEnv->hasRequireAlias('my\\project', Symbol::create('package')));
        self::assertSame('vendor\\package', $this->globalEnv->resolveAlias('package'));

        $phpClassNode = $this->globalEnv->resolve(Symbol::create('Library'), NodeEnvironment::empty());
        self::assertInstanceOf(PhpClassNameNode::class, $phpClassNode);
        self::assertSame('\\Vendor\\Library', $phpClassNode->getName()->getName());

        Phel::addDefinition('vendor\\package', 'foo', 'value', Phel::map());
        $globalVarNode = $this->globalEnv->resolve(Symbol::create('foo'), NodeEnvironment::empty());
        self::assertInstanceOf(GlobalVarNode::class, $globalVarNode);
        self::assertSame('vendor\\package', $globalVarNode->getNamespace());
    }
}
