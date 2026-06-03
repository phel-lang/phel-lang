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
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\AbstractLocatedException;
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

        new DefEnumSymbol($this->analyzer)->analyze($list, NodeEnvironment::empty());
    }

    public function test_first_arg_is_not_symbol(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("First argument of 'defenum must be a Symbol");

        $list = Phel::list([Symbol::create(Symbol::NAME_DEF_ENUM), 'no-symbol']);

        new DefEnumSymbol($this->analyzer)->analyze($list, NodeEnvironment::empty());
    }

    public function test_requires_at_least_one_case(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("'defenum requires at least one case");

        $list = Phel::list([Symbol::create(Symbol::NAME_DEF_ENUM), Symbol::create('Empty')]);

        new DefEnumSymbol($this->analyzer)->analyze($list, NodeEnvironment::empty());
    }

    public function test_case_must_be_a_keyword(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('Each enum case must be a keyword');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF_ENUM),
            Symbol::create('Bad'),
            'not-a-keyword',
        ]);

        new DefEnumSymbol($this->analyzer)->analyze($list, NodeEnvironment::empty());
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

        new DefEnumSymbol($this->analyzer)->analyze($list, NodeEnvironment::empty());
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

        new DefEnumSymbol($this->analyzer)->analyze($list, NodeEnvironment::empty());
    }

    public function test_string_backed_enum(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF_ENUM),
            Symbol::create('Status'),
            Keyword::create('active'), 'active',
            Keyword::create('inactive'), 'inactive',
        ]);

        $node = new DefEnumSymbol($this->analyzer)->analyze($list, NodeEnvironment::empty());

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

        $node = new DefEnumSymbol($this->analyzer)->analyze($list, NodeEnvironment::empty());

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

        $node = new DefEnumSymbol($this->analyzer)->analyze($list, NodeEnvironment::empty());

        self::assertNull($node->getBackingType());
        self::assertCount(2, $node->getCases());
        self::assertNull($node->getCases()[0]->getValue());
    }
}
