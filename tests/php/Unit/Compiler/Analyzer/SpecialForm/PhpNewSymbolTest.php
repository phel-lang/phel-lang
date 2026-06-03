<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\PhpNamedArgNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpNewSymbol;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\AbstractLocatedException;
use PHPUnit\Framework\TestCase;

final class PhpNewSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function test_named_args_after_marker_become_named_arg_nodes(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_PHP_NEW),
            Symbol::create('\\DateTime'),
            'now',
            Keyword::create('&'),
            Keyword::create('timezone'),
            null,
        ]);

        $node = new PhpNewSymbol($this->analyzer)->analyze($list, NodeEnvironment::empty());

        $args = $node->getArgs();
        self::assertCount(2, $args, 'one positional arg and one named arg');
        self::assertNotInstanceOf(PhpNamedArgNode::class, $args[0]);
        self::assertInstanceOf(PhpNamedArgNode::class, $args[1]);
        self::assertSame('timezone', $args[1]->getName());
    }

    public function test_keywords_before_the_marker_stay_positional(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_PHP_NEW),
            Symbol::create('\\DateTime'),
            Keyword::create('not-a-name'),
        ]);

        $node = new PhpNewSymbol($this->analyzer)->analyze($list, NodeEnvironment::empty());

        $args = $node->getArgs();
        self::assertCount(1, $args);
        self::assertNotInstanceOf(PhpNamedArgNode::class, $args[0]);
    }

    public function test_missing_value_for_named_arg_throws(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("Missing value for named argument ':timezone'");

        $list = Phel::list([
            Symbol::create(Symbol::NAME_PHP_NEW),
            Symbol::create('\\DateTime'),
            Keyword::create('&'),
            Keyword::create('timezone'),
        ]);

        new PhpNewSymbol($this->analyzer)->analyze($list, NodeEnvironment::empty());
    }

    public function test_non_keyword_after_marker_throws(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('must be :key value pairs');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_PHP_NEW),
            Symbol::create('\\DateTime'),
            Keyword::create('&'),
            'not-a-keyword',
            1,
        ]);

        new PhpNewSymbol($this->analyzer)->analyze($list, NodeEnvironment::empty());
    }

    public function test_empty_after_marker_throws(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('must be followed by :key value pairs');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_PHP_NEW),
            Symbol::create('\\DateTime'),
            Keyword::create('&'),
        ]);

        new PhpNewSymbol($this->analyzer)->analyze($list, NodeEnvironment::empty());
    }
}
