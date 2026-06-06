<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\DefEnumNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\DefEnumSymbol;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\InterfaceImplementationsAnalyzer;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\MethodBodyAnalyzer;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpBlockAnalyzer;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Munge;
use PHPUnit\Framework\TestCase;

final class DefEnumSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function test_with_no_arguments(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("At least one argument is required for 'defenum");

        $list = Phel::list([Symbol::create(Symbol::NAME_DEF_ENUM)]);

        $this->analyze($list);
    }

    public function test_first_arg_is_not_symbol(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("First argument of 'defenum must be a Symbol");

        $list = Phel::list([Symbol::create(Symbol::NAME_DEF_ENUM), 'no-symbol']);

        $this->analyze($list);
    }

    public function test_requires_at_least_one_case(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("'defenum requires at least one case");

        $list = Phel::list([Symbol::create(Symbol::NAME_DEF_ENUM), Symbol::create('Empty')]);

        $this->analyze($list);
    }

    public function test_non_case_non_implementation_form_is_rejected(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('Expected a interface name in defenum');

        // `:a 1` is a backed case; the trailing string is neither a `:php`
        // marker nor an interface symbol, so it is an invalid implementation.
        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF_ENUM),
            Symbol::create('Bad'),
            Keyword::create('a'), 1,
            'not-an-interface',
        ]);

        $this->analyze($list);
    }

    public function test_mixed_value_types_are_rejected(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('Enum case values must be all int or all string');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF_ENUM),
            Symbol::create('Mixed'),
            Keyword::create('a'), 1,
            Keyword::create('b'), 'two',
        ]);

        $this->analyze($list);
    }

    public function test_partial_values_are_rejected(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('Enum cases must either all have a value or none');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF_ENUM),
            Symbol::create('Partial'),
            Keyword::create('a'), 1,
            Keyword::create('b'),
        ]);

        $this->analyze($list);
    }

    public function test_string_backed_enum(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF_ENUM),
            Symbol::create('Status'),
            Keyword::create('active'), 'active',
            Keyword::create('inactive'), 'inactive',
        ]);

        $node = $this->analyze($list);

        self::assertInstanceOf(DefEnumNode::class, $node);
        self::assertSame('string', $node->getBackingType());
        self::assertCount(2, $node->getCases());
        self::assertSame('active', $node->getCases()[0]->getName());
        self::assertSame('active', $node->getCases()[0]->getValue());
    }

    public function test_int_backed_enum(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF_ENUM),
            Symbol::create('Priority'),
            Keyword::create('low'), 1,
            Keyword::create('high'), 10,
        ]);

        $node = $this->analyze($list);

        self::assertSame('int', $node->getBackingType());
        self::assertSame(10, $node->getCases()[1]->getValue());
    }

    public function test_pure_enum_has_no_backing_type(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF_ENUM),
            Symbol::create('Color'),
            Keyword::create('red'),
            Keyword::create('green'),
        ]);

        $node = $this->analyze($list);

        self::assertNull($node->getBackingType());
        self::assertCount(2, $node->getCases());
        self::assertNull($node->getCases()[0]->getValue());
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    private function analyze(PersistentListInterface $list): DefEnumNode
    {
        $munge = new Munge();
        $implementationsAnalyzer = new InterfaceImplementationsAnalyzer(
            $this->analyzer,
            $munge,
            new MethodBodyAnalyzer($this->analyzer),
            new PhpBlockAnalyzer($munge, new MethodBodyAnalyzer($this->analyzer)),
        );

        return new DefEnumSymbol($this->analyzer, $implementationsAnalyzer)
            ->analyze($list, NodeEnvironment::empty());
    }
}
