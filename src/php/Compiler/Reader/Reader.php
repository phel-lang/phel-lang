<?php

declare(strict_types=1);

namespace Phel\Compiler\Reader;

use Phel\Compiler\Lexer\Token;
use Phel\Compiler\Parser\ParserNode\AbstractAtomNode;
use Phel\Compiler\Parser\ParserNode\ListNode;
use Phel\Compiler\Parser\ParserNode\MetaNode;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Parser\ParserNode\QuoteNode;
use Phel\Compiler\Parser\ParserNode\SymbolNode;
use Phel\Compiler\Parser\ParserNode\TriviaNodeInterface;
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

    private ExpressionReaderFactoryInterface $readerFactory;
    private QuasiquoteTransformerInterface $quasiquoteTransformer;

    public function __construct(
        ExpressionReaderFactoryInterface $readerFactory,
        QuasiquoteTransformerInterface $quasiquoteTransformer
    ) {
        $this->readerFactory = $readerFactory;
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
        if ($node instanceof SymbolNode) {
            return $this->readSymbolNode($node);
        }

        if ($node instanceof AbstractAtomNode) {
            return $this->readAtomNode($node);
        }

        if ($node instanceof ListNode) {
            return $this->readListNode($node);
        }

        if ($node instanceof QuoteNode) {
            return $this->readQuoteNode($node);
        }

        if ($node instanceof MetaNode) {
            return $this->readMetaNode($node);
        }

        throw ReaderException::forNode($node, 'Unterminated list');
    }

    private function readSymbolNode(SymbolNode $node): Symbol
    {
        return $this->readerFactory
            ->createSymbolReader()
            ->read($node, $this->fnArgs);
    }

    /**
     * @return AbstractType|string|float|int|bool|null
     */
    private function readAtomNode(AbstractAtomNode $node)
    {
        return $this->readerFactory
            ->createAtomReader()
            ->read($node);
    }

    /**
     * @return Tuple|PhelArray|Table
     */
    private function readListNode(ListNode $node)
    {
        if ($node->getTokenType() === Token::T_OPEN_PARENTHESIS) {
            return $this->readerFactory
                ->createListReader($this)
                ->read($node);
        }

        if ($node->getTokenType() === Token::T_OPEN_BRACKET) {
            return $this->readerFactory
                ->createListReader($this)
                ->readUsingBrackets($node);
        }

        if ($node->getTokenType() === Token::T_ARRAY) {
            return $this->readerFactory
                ->createListArrayReader($this)
                ->read($node);
        }

        if ($node->getTokenType() === Token::T_TABLE) {
            return $this->readerFactory
                ->createListTableReader($this)
                ->read($node);
        }

        if ($node->getTokenType() === Token::T_FN) {
            $this->fnArgs = [];

            return $this->readerFactory
                ->createListFnReader($this)
                ->read($node, $this->fnArgs);
        }

        throw new RuntimeException('Not a valid ListNode: ' . get_class($node));
    }

    /**
     * @return AbstractType|string|float|int|bool|null
     */
    private function readQuoteNode(QuoteNode $node)
    {
        if ($node->getTokenType() === Token::T_QUOTE) {
            return $this->readerFactory
                ->createWrapReader($this)
                ->read($node, Symbol::NAME_QUOTE);
        }

        if ($node->getTokenType() === Token::T_UNQUOTE) {
            return $this->readerFactory
                ->createWrapReader($this)
                ->read($node, Symbol::NAME_UNQUOTE);
        }

        if ($node->getTokenType() === Token::T_UNQUOTE_SPLICING) {
            return $this->readerFactory
                ->createWrapReader($this)
                ->read($node, Symbol::NAME_UNQUOTE_SPLICING);
        }

        if ($node->getTokenType() === Token::T_QUASIQUOTE) {
            return $this->readerFactory
                ->createQuoasiquoteReader($this, $this->quasiquoteTransformer)
                ->read($node);
        }

        throw new RuntimeException('Not a valid QuoteNode: ' . get_class($node));
    }

    /**
     * @return AbstractType|string|float|int|bool
     */
    private function readMetaNode(MetaNode $node)
    {
        return $this->readerFactory
            ->createMetaReader($this)
            ->read($node);
    }
}
