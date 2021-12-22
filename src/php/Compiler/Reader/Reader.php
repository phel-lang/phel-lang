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
use Phel\Compiler\Reader\Exceptions\NotValidQuoteNodeException;
use Phel\Compiler\Reader\Exceptions\ReaderException;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
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
            throw ReaderException::forNode($parseTree, $parseTree, 'Cannot read from whitespace or comments');
        }

        return new ReaderResult(
            $this->readExpression($parseTree, $parseTree),
            CodeSnippet::fromNode($parseTree)
        );
    }

    /**
     * @throws ReaderException
     *
     * @return TypeInterface|string|float|int|bool|null
     */
    public function readExpression(NodeInterface $node, NodeInterface $root)
    {
        if ($node instanceof SymbolNode) {
            return $this->readSymbolNode($node);
        }

        if ($node instanceof AbstractAtomNode) {
            return $this->readAtomNode($node);
        }

        if ($node instanceof ListNode) {
            return $this->readListNode($node, $root);
        }

        if ($node instanceof QuoteNode) {
            return $this->readQuoteNode($node, $root);
        }

        if ($node instanceof MetaNode) {
            return $this->readMetaNode($node, $root);
        }

        throw ReaderException::forNode($node, $root, 'Unterminated list');
    }

    private function readSymbolNode(SymbolNode $node): Symbol
    {
        return $this->readerFactory
            ->createSymbolReader()
            ->read($node, $this->fnArgs);
    }

    /**
     * @return TypeInterface|string|float|int|bool|null
     */
    private function readAtomNode(AbstractAtomNode $node)
    {
        return $this->readerFactory
            ->createAtomReader()
            ->read($node);
    }

    private function readListNode(ListNode $node, NodeInterface $root): TypeInterface
    {
        if ($node->getTokenType() === Token::T_OPEN_PARENTHESIS) {
            return $this->readerFactory
                ->createListReader($this)
                ->read($node, $root);
        }

        if ($node->getTokenType() === Token::T_OPEN_BRACKET) {
            return $this->readerFactory
                ->createVectorReader($this)
                ->read($node, $root);
        }

        if ($node->getTokenType() === Token::T_OPEN_BRACE) {
            return $this->readerFactory
                ->createMapReader($this)
                ->read($node, $root);
        }

        if ($node->getTokenType() === Token::T_FN) {
            $this->fnArgs = [];

            return $this->readerFactory
                ->createListFnReader($this)
                ->read($node, $this->fnArgs, $root);
        }

        throw new RuntimeException('Not a valid ListNode: ' . get_class($node));
    }

    /**
     * @throws NotValidQuoteNodeException
     *
     * @return TypeInterface|string|float|int|bool|null
     */
    private function readQuoteNode(QuoteNode $node, NodeInterface $root)
    {
        if ($node->getTokenType() === Token::T_QUOTE) {
            return $this->readerFactory
                ->createWrapReader($this)
                ->read($node, Symbol::NAME_QUOTE, $root);
        }

        if ($node->getTokenType() === Token::T_UNQUOTE) {
            return $this->readerFactory
                ->createWrapReader($this)
                ->read($node, Symbol::NAME_UNQUOTE, $root);
        }

        if ($node->getTokenType() === Token::T_UNQUOTE_SPLICING) {
            return $this->readerFactory
                ->createWrapReader($this)
                ->read($node, Symbol::NAME_UNQUOTE_SPLICING, $root);
        }

        if ($node->getTokenType() === Token::T_QUASIQUOTE) {
            return $this->readerFactory
                ->createQuoasiquoteReader($this, $this->quasiquoteTransformer)
                ->read($node, $root);
        }

        throw NotValidQuoteNodeException::forNode($node);
    }

    /**
     * @throws ReaderException
     *
     * @return TypeInterface|string|float|int|bool
     */
    private function readMetaNode(MetaNode $node, NodeInterface $root)
    {
        return $this->readerFactory
            ->createMetaReader($this)
            ->read($node, $root);
    }
}
