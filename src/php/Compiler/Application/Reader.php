<?php

declare(strict_types=1);

namespace Phel\Compiler\Application;

use Phel\Compiler\Domain\Lexer\Token;
use Phel\Compiler\Domain\Parser\ParserNode\AbstractAtomNode;
use Phel\Compiler\Domain\Parser\ParserNode\ListNode;
use Phel\Compiler\Domain\Parser\ParserNode\MetaNode;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\QuoteNode;
use Phel\Compiler\Domain\Parser\ParserNode\SymbolNode;
use Phel\Compiler\Domain\Parser\ParserNode\TaggedLiteralNode;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Domain\Parser\ReadModel\CodeSnippet;
use Phel\Compiler\Domain\Parser\ReadModel\ReaderResult;
use Phel\Compiler\Domain\Reader\Exceptions\NotValidQuoteNodeException;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Compiler\Domain\Reader\ExpressionReaderFactoryInterface;
use Phel\Compiler\Domain\Reader\QuasiquoteTransformerInterface;
use Phel\Compiler\Domain\Reader\ReaderInterface;
use Phel\Lang\MetaInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use RuntimeException;

use function sprintf;

final class Reader implements ReaderInterface
{
    /** @var array<int,Symbol>|null */
    private ?array $fnArgs = null;

    private ?string $fnPlaceholderPrefix = null;

    public function __construct(
        private readonly ExpressionReaderFactoryInterface $readerFactory,
        private readonly QuasiquoteTransformerInterface $quasiquoteTransformer,
    ) {}

    /**
     * Reads the next expression from the token stream.
     *
     * If the token stream reaches the end, null is returned.
     *
     * @param NodeInterface $node The token stream to read
     *
     * @throws ReaderException
     */
    public function read(NodeInterface $node): ReaderResult
    {
        if ($node instanceof TriviaNodeInterface) {
            throw ReaderException::forNode($node, $node, 'Cannot read from whitespace or comments');
        }

        return new ReaderResult(
            $this->readExpression($node, $node),
            CodeSnippet::fromNode($node),
        );
    }

    /**
     * @throws ReaderException
     */
    public function readExpression(
        NodeInterface $node,
        NodeInterface $root,
    ): Symbol|float|bool|int|string|TypeInterface|MetaInterface|null {
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

        // Phel does not (yet) register any tagged-literal handlers. Reaching
        // this point means a `#<tag>` form survived parsing in a code path
        // that is actually emitted — i.e. the selected `#?` branch or
        // top-level code. Unselected branches of `#?` are discarded by the
        // parser, so unknown tags inside `:jank`/`:clj`/... never reach here.
        if ($node instanceof TaggedLiteralNode) {
            throw ReaderException::forNode(
                $node,
                $root,
                sprintf(
                    "Unknown tagged literal '#%s'. Phel has no built-in handler for this tag; it may only appear inside a non-selected reader-conditional branch (e.g. :clj, :jank).",
                    $node->getTag(),
                ),
            );
        }

        throw ReaderException::forNode($node, $root, 'Unterminated list');
    }

    private function readSymbolNode(SymbolNode $node): Symbol
    {
        return $this->readerFactory
            ->createSymbolReader()
            ->read($node, $this->fnArgs, $this->fnPlaceholderPrefix ?? '$');
    }

    private function readAtomNode(AbstractAtomNode $node): float|bool|int|string|TypeInterface|null
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

        if ($node->getTokenType() === Token::T_HASH_OPEN_BRACE) {
            return $this->readerFactory
                ->createSetReader($this)
                ->read($node, $root);
        }

        $fnPrefix = match ($node->getTokenType()) {
            Token::T_FN => '$',
            Token::T_HASH_FN => '%',
            default => null,
        };

        if ($fnPrefix !== null) {
            $this->fnArgs = [];
            $this->fnPlaceholderPrefix = $fnPrefix;
            $result = $this->readerFactory
                ->createListFnReader($this)
                ->read($node, $this->fnArgs, $root);
            $this->fnPlaceholderPrefix = null;

            return $result;
        }

        throw new RuntimeException('Not a valid ListNode: ' . $node::class);
    }

    /**
     * @throws NotValidQuoteNodeException
     */
    private function readQuoteNode(QuoteNode $node, NodeInterface $root): float|bool|int|string|TypeInterface|null
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

        if ($node->getTokenType() === Token::T_DEREF) {
            return $this->readerFactory
                ->createWrapReader($this)
                ->read($node, Symbol::NAME_DEREF, $root);
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
     */
    private function readMetaNode(MetaNode $node, NodeInterface $root): MetaInterface
    {
        return $this->readerFactory
            ->createMetaReader($this)
            ->read($node, $root);
    }
}
