<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Compiler\Parser\ExpressionReader\AtomReader;
use Phel\Compiler\Parser\ExpressionReader\ListArrayReader;
use Phel\Compiler\Parser\ExpressionReader\ListFnReader;
use Phel\Compiler\Parser\ExpressionReader\ListReader;
use Phel\Compiler\Parser\ExpressionReader\ListTableReader;
use Phel\Compiler\Parser\ExpressionReader\MetaReader;
use Phel\Compiler\Parser\ExpressionReader\QuoasiquoteReader;
use Phel\Compiler\Parser\ExpressionReader\SymbolReader;
use Phel\Compiler\Parser\ExpressionReader\WrapReader;
use Phel\Compiler\Parser\ParserNode\AbstractAtomNode;
use Phel\Compiler\Parser\ParserNode\ListNode;
use Phel\Compiler\Parser\ParserNode\MetaNode;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Parser\ParserNode\QuoteNode;
use Phel\Compiler\Parser\ParserNode\SymbolNodeAbstract;
use Phel\Compiler\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Parser\QuasiquoteTransformerInterface;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Phel\Compiler\Parser\ReadModel\ReaderResult;
use Phel\Exceptions\ReaderException;
use Phel\Lang\AbstractType;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use RuntimeException;

final class Reader implements ReaderInterface
{
    /** @var Symbol[]|null */
    private ?array $fnArgs = null;

    private QuasiquoteTransformerInterface $quasiquoteTransformer;

    public function __construct(QuasiquoteTransformerInterface $quasiquoteTransformer)
    {
        $this->quasiquoteTransformer = $quasiquoteTransformer;
    }

    /**
     * Reads the next expression from the token stream.
     *
     * If the token stream reaches the end, null is returned.
     *
     * @param NodeInterface $tokenStream The token stream to read
     *
     * @throws ReaderException
     */
    public function read(NodeInterface $parseTree): ReaderResult
    {
        if ($parseTree instanceof TriviaNodeInterface) {
            throw ReaderException::forNode($parseTree, 'Can not read from whitespace or comments');
        }

        return new ReaderResult(
            $this->readExpression($parseTree),
            CodeSnippet::fromNode($parseTree)
        );
    }

    /**
     * @throws ReaderException
     *
     * @return AbstractType|string|float|int|bool|null
     */
    public function readExpression(NodeInterface $node)
    {
        if ($node instanceof SymbolNodeAbstract) {
            return $this->readSymbol($node);
        }

        if ($node instanceof AbstractAtomNode) {
            return $this->readAtom($node);
        }

        if ($node instanceof ListNode) {
            return $this->readListNode($node);
        }

        if ($node instanceof QuoteNode) {
            return $this->readQuoteNode($node);
        }

        if ($node instanceof MetaNode) {
            return $this->readMeta($node);
        }

        throw ReaderException::forNode($node, 'Unterminated list');
    }

    private function readSymbol(SymbolNodeAbstract $node): Symbol
    {
        return (new SymbolReader())->read($node, $this->fnArgs);
    }

    /**
     * @return AbstractType|string|float|int|bool|null
     */
    private function readAtom(AbstractAtomNode $node)
    {
        return (new AtomReader())->read($node);
    }

    /**
     * @return Tuple|PhelArray|Table
     */
    private function readListNode(ListNode $node)
    {
        if ($node->getTokenType() === Token::T_OPEN_PARENTHESIS) {
            return (new ListReader($this))->read($node);
        }

        if ($node->getTokenType() === Token::T_OPEN_BRACKET) {
            return (new ListReader($this))->readUsingBrackets($node);
        }

        if ($node->getTokenType() === Token::T_ARRAY) {
            return (new ListArrayReader($this))->read($node);
        }

        if ($node->getTokenType() === Token::T_TABLE) {
            return (new ListTableReader($this))->read($node);
        }

        if ($node->getTokenType() === Token::T_FN) {
            $this->fnArgs = [];

            return (new ListFnReader($this))->read($node, $this->fnArgs);
        }

        throw new RuntimeException('Not a valid ListNode: ' . get_class($node));
    }

    /**
     * @return AbstractType|string|float|int|bool|null
     */
    private function readQuoteNode(QuoteNode $node)
    {
        if ($node->getTokenType() === Token::T_QUOTE) {
            return (new WrapReader($this))->read($node, Symbol::NAME_QUOTE);
        }

        if ($node->getTokenType() === Token::T_UNQUOTE) {
            return (new WrapReader($this))->read($node, Symbol::NAME_UNQUOTE);
        }

        if ($node->getTokenType() === Token::T_UNQUOTE_SPLICING) {
            return (new WrapReader($this))->read($node, Symbol::NAME_UNQUOTE_SPLICING);
        }

        if ($node->getTokenType() === Token::T_QUASIQUOTE) {
            return (new QuoasiquoteReader($this, $this->quasiquoteTransformer))->read($node);
        }

        throw new RuntimeException('Not a valid QuoteNode: ' . get_class($node));
    }

    /**
     * @return AbstractType|string|float|int|bool
     */
    private function readMeta(MetaNode $node)
    {
        return (new MetaReader($this))->read($node);
    }
}
