<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Compiler\Parser\ExpressionReader\AtomReader;
use Phel\Compiler\Parser\ExpressionReader\ListFnReader;
use Phel\Compiler\Parser\ExpressionReader\ListReader;
use Phel\Compiler\Parser\ExpressionReader\MetaReader;
use Phel\Compiler\Parser\ExpressionReader\QuoasiquoteReader;
use Phel\Compiler\Parser\ExpressionReader\SymbolReader;
use Phel\Compiler\Parser\ExpressionReader\WrapReader;
use Phel\Compiler\Parser\ParserNode\AtomNode;
use Phel\Compiler\Parser\ParserNode\ListNode;
use Phel\Compiler\Parser\ParserNode\MetaNode;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Parser\ParserNode\QuoteNode;
use Phel\Compiler\Parser\ParserNode\SymbolNode;
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
            throw $this->buildReaderException('Can not read from whitespace or comments', $parseTree);
        }

        return new ReaderResult(
            $this->readExpression($parseTree),
            $this->getCodeSnippet($parseTree)
        );
    }

    public function buildReaderException(string $message, NodeInterface $node): ReaderException
    {
        $codeSnippet = $this->getCodeSnippet($node);

        return new ReaderException(
            $message,
            $codeSnippet->getStartLocation(),
            $codeSnippet->getEndLocation(),
            $codeSnippet
        );
    }

    /**
     * Create a CodeSnippet from a list of Tokens.
     *
     * @param NodeInterface $node The current node
     */
    private function getCodeSnippet(NodeInterface $node): CodeSnippet
    {
        return new CodeSnippet(
            $node->getStartLocation(),
            $node->getEndLocation(),
            $node->getCode()
        );
    }

    /**
     * @throws ReaderException
     *
     * @return AbstractType|string|float|int|bool|null
     */
    public function readExpression(NodeInterface $node)
    {
        if ($node instanceof SymbolNode) {
            return $this->readSymbol($node);
        }

        if ($node instanceof AtomNode) {
            return $this->readAtom($node);
        }

        if ($node instanceof ListNode && $node->getTokenType() === Token::T_OPEN_PARENTHESIS) {
            return $this->readListOpenParenthesis($node);
        }

        if ($node instanceof ListNode && $node->getTokenType() === Token::T_OPEN_BRACKET) {
            return $this->readListOpenBracket($node);
        }

        if ($node instanceof ListNode && $node->getTokenType() === Token::T_ARRAY) {
            return $this->readListArray($node);
        }

        if ($node instanceof ListNode && $node->getTokenType() === Token::T_TABLE) {
            return $this->readListTable($node);
        }

        if ($node instanceof ListNode && $node->getTokenType() === Token::T_FN) {
            return $this->readListFn($node);
        }

        if ($node instanceof QuoteNode && $node->getTokenType() === Token::T_QUOTE) {
            return $this->readQuote($node);
        }

        if ($node instanceof QuoteNode && $node->getTokenType() === Token::T_UNQUOTE) {
            return $this->readUnquote($node);
        }

        if ($node instanceof QuoteNode && $node->getTokenType() === Token::T_UNQUOTE_SPLICING) {
            return $this->readUnquoteSplicing($node);
        }

        if ($node instanceof QuoteNode && $node->getTokenType() === Token::T_QUASIQUOTE) {
            return $this->readQuasiquote($node);
        }

        if ($node instanceof MetaNode) {
            return $this->readMeta($node);
        }

        throw $this->buildReaderException('Unterminated list', $node);
    }

    private function readSymbol(SymbolNode $node): Symbol
    {
        return (new SymbolReader())->read($node, $this->fnArgs);
    }

    /**
     * @return AbstractType|string|float|int|bool|null
     */
    private function readAtom(AtomNode $node)
    {
        return (new AtomReader())->read($node);
    }

    private function readListOpenParenthesis(ListNode $node): Tuple
    {
        return (new ListReader($this))->read($node);
    }

    private function readListOpenBracket(ListNode $node): Tuple
    {
        return (new ListReader($this))->readUsingBrackets($node);
    }

    private function readListArray(ListNode $node): PhelArray
    {
        $tuple = (new ListReader($this))->read($node);

        return PhelArray::fromTuple($tuple);
    }

    private function readListTable(ListNode $node): Table
    {
        $tuple = (new ListReader($this))->read($node);

        if (!$tuple->hasEvenNumberOfParams()) {
            throw $this->buildReaderException('Tables must have an even number of parameters', $node);
        }

        return Table::fromTuple($tuple);
    }

    private function readListFn(ListNode $node): Tuple
    {
        $this->fnArgs = [];

        return (new ListFnReader($this))->read($node, $this->fnArgs);
    }

    private function readQuote(QuoteNode $node): Tuple
    {
        return (new WrapReader($this))->read($node, Symbol::NAME_QUOTE);
    }

    private function readUnquote(QuoteNode $node): Tuple
    {
        return (new WrapReader($this))->read($node, Symbol::NAME_UNQUOTE);
    }

    private function readUnquoteSplicing(QuoteNode $node): Tuple
    {
        return (new WrapReader($this))->read($node, Symbol::NAME_UNQUOTE_SPLICING);
    }

    /**
     * @return AbstractType|string|float|int|bool|null
     */
    private function readQuasiquote(QuoteNode $node)
    {
        return (new QuoasiquoteReader($this, $this->quasiquoteTransformer))->read($node);
    }

    /**
     * @return AbstractType|string|float|int|bool
     */
    private function readMeta(MetaNode $node)
    {
        return (new MetaReader($this))->read($node);
    }
}
