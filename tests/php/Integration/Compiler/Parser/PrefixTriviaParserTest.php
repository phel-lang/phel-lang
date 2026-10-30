<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Parser;

use Phel;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Domain\Parser\Exceptions\UnexpectedParserException;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Formatter\FormatterFactory;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\Parser\Node\MetaNode;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\QuoteNode;
use Phel\Shared\Printer\Printer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function count;

final class PrefixTriviaParserTest extends TestCase
{
    protected function setUp(): void
    {
        Phel::bootstrap(__DIR__);
        GlobalEnvironmentSingleton::initializeNew();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function quoted_forms(): iterable
    {
        foreach (["'", '@', '`', '~', '~@', "#'"] as $prefix) {
            foreach ([' ', "\n", "; keep this\n", ', '] as $gap) {
                $source = $prefix . $gap . 'x';
                yield $source => [$source];
            }
        }
    }

    #[DataProvider('quoted_forms')]
    public function test_quote_prefix_retains_trivia_and_one_semantic_child(string $source): void
    {
        $node = $this->parse($source);

        self::assertInstanceOf(QuoteNode::class, $node);
        self::assertSame($source, $node->getCode());
        self::assertSame('x', $node->getExpression()->getCode());
        self::assertSame([$node->getExpression()], $node->getChildren());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function metadata_forms(): iterable
    {
        foreach (['^ :a x', "^; keep meta\n:a x", "^\n:a ; keep value\nx"] as $source) {
            yield $source => [$source];
        }
    }

    #[DataProvider('metadata_forms')]
    public function test_metadata_prefix_retains_trivia_and_semantic_nodes(string $source): void
    {
        $node = $this->parse($source);

        self::assertInstanceOf(MetaNode::class, $node);
        self::assertSame($source, $node->getCode());
        self::assertSame(':a', $node->getMetaNode()->getCode());
        self::assertSame('x', $node->getObjectNode()->getCode());
        $value = new CompilerFacade()->read($node)->getAst();
        self::assertInstanceOf(Symbol::class, $value);
        self::assertTrue($value->getMeta()?->find(Keyword::create('a')));
    }

    public function test_reading_quote_with_whitespace_has_the_normal_meaning(): void
    {
        $node = $this->parse("' x");

        self::assertSame('(quote x)', Printer::readable()->print(new CompilerFacade()->read($node)->getAst()));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function incomplete_prefixes(): iterable
    {
        foreach (["'", '@', '`', '~', '~@', "#'", '^'] as $prefix) {
            yield $prefix => [$prefix, $prefix];
            yield $prefix . ' comment' => [$prefix . "; nothing follows\n", $prefix];
        }

        yield 'metadata object missing' => ['^ :a', '^'];
        yield 'quote before closer' => ["(' )", "'"];
    }

    #[DataProvider('incomplete_prefixes')]
    public function test_incomplete_prefix_error_names_the_prefix(string $source, string $prefix): void
    {
        $this->expectException(UnexpectedParserException::class);
        $this->expectExceptionMessage('reader prefix ' . $prefix);

        $this->parse($source);
    }

    public function test_formatter_preserves_prefix_comments_and_is_idempotent(): void
    {
        $source = "(def x ' ; keep quote\n  ( foo ))\n(def y ^ ; keep meta\n  :a ; keep object\n  ( foo ))\n";
        $formatter = new FormatterFactory()->createFormatter();
        $formatted = $formatter->format($source);

        self::assertNotSame($source, $formatted);
        self::assertStringContainsString('(foo)', $formatted);
        self::assertStringContainsString('; keep quote', $formatted);
        self::assertStringContainsString('; keep meta', $formatted);
        self::assertStringContainsString('; keep object', $formatted);
        self::assertSame($formatted, $formatter->format($formatted));
    }

    public function test_quote_trivia_survives_cloning_and_child_replacement(): void
    {
        $source = "' ; keep quote\n(foo  bar)";
        $node = $this->parse($source);
        self::assertInstanceOf(QuoteNode::class, $node);
        $copy = clone $node;
        $copy->replaceChildren([$this->parse('(foo bar)')]);

        self::assertSame("' ; keep quote\n(foo bar)", $copy->getCode());
        self::assertSame($source, $node->getCode());
    }

    public function test_meta_trivia_survives_cloning_and_child_replacement(): void
    {
        $source = "^ ; keep meta\n:a (foo  bar)";
        $node = $this->parse($source);
        self::assertInstanceOf(MetaNode::class, $node);
        $copy = clone $node;
        $children = $copy->getChildren();
        $children[count($children) - 1] = $this->parse('(foo bar)');
        $copy->replaceChildren($children);

        self::assertSame("^ ; keep meta\n:a (foo bar)", $copy->getCode());
        self::assertSame($source, $node->getCode());
    }

    private function parse(string $source): NodeInterface
    {
        $compiler = new CompilerFacade();

        return $compiler->parseNext($compiler->lexString($source));
    }
}
