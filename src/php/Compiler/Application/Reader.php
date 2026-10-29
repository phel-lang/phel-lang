<?php

declare(strict_types=1);

namespace Phel\Compiler\Application;

use Phel\Compiler\Domain\Reader\Exceptions\NotValidQuoteNodeException;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Compiler\Domain\Reader\ExpressionReaderFactoryInterface;
use Phel\Compiler\Domain\Reader\QuasiquoteTransformerInterface;
use Phel\Compiler\Domain\Reader\ReaderInterface;
use Phel\Lang\MetaInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Parser\Node\AbstractAtomNode;
use Phel\Shared\Parser\Node\ListNode;
use Phel\Shared\Parser\Node\MetaNode;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\QuoteNode;
use Phel\Shared\Parser\Node\ReaderConditionalNode;
use Phel\Shared\Parser\Node\ReaderCondSplicingNode;
use Phel\Shared\Parser\Node\SymbolNode;
use Phel\Shared\Parser\Node\TaggedLiteralNode;
use Phel\Shared\Parser\Node\Token;
use Phel\Shared\Parser\Node\TriviaNodeInterface;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use Phel\Shared\ReaderResult;
use RuntimeException;

/**
 * @internal
 */
final class Reader implements ReaderInterface
{
    /** @var array<int,Symbol>|null */
    private ?array $fnArgs = null;

    private ?string $fnPlaceholderPrefix = null;

    private int $quasiquoteDepth = 0;

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
            new CodeSnippet($node->getStartLocation(), $node->getEndLocation(), $node->getCode()),
        );
    }

    /**
     * @throws ReaderException
     */
    public function readExpression(
        NodeInterface $node,
        NodeInterface $root,
    ): mixed {
        if ($node instanceof ReaderConditionalNode) {
            return $this->readExpression($node->getSelected(), $root);
        }

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

        if ($node instanceof TaggedLiteralNode) {
            $form = $node->getForm();
            while ($form instanceof ReaderConditionalNode) {
                $form = $form->getSelected();
            }

            if ($form instanceof ListNode) {
                $form = $this->resolveListChildren($form);
            }

            $node = new TaggedLiteralNode($node->getTag(), $form, $node->getStartLocation(), $node->getEndLocation());

            return $this->readerFactory
                ->createTaggedLiteralReader($this)
                ->read($node, $root);
        }

        throw ReaderException::forNode($node, $root, 'Unterminated list');
    }

    private function readSymbolNode(SymbolNode $node): Symbol
    {
        return $this->readerFactory
            ->createSymbolReader()
            ->read($node, $this->fnArgs, $this->fnPlaceholderPrefix ?? '$');
    }

    /**
     * @param AbstractAtomNode<mixed> $node
     */
    private function readAtomNode(AbstractAtomNode $node): float|bool|int|string|TypeInterface|null
    {
        return $this->readerFactory
            ->createAtomReader()
            ->read($node);
    }

    private function readListNode(ListNode $node, NodeInterface $root): TypeInterface
    {
        $node = $this->resolveListChildren($node);

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

    private function resolveListChildren(ListNode $node): ListNode
    {
        $children = $this->expandChildren($node->getChildren());
        if ($children === $node->getChildren()) {
            return $node;
        }

        return new ListNode($node->getTokenType(), $node->getStartLocation(), $node->getEndLocation(), $children);
    }

    /**
     * @param list<NodeInterface> $children
     *
     * @return list<NodeInterface>
     */
    private function expandChildren(array $children): array
    {
        $result = [];
        foreach ($children as $child) {
            if ($child instanceof ReaderConditionalNode) {
                array_push($result, ...$this->expandChildren([$child->getSelected()]));
            } elseif ($child instanceof ReaderCondSplicingNode) {
                array_push($result, ...$this->expandChildren($child->getChildren()));
            } else {
                $result[] = $child;
            }
        }

        return $result;
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

        if ($node->getTokenType() === Token::T_UNQUOTE || $node->getTokenType() === Token::T_UNQUOTE_SPLICING) {
            if ($this->quasiquoteDepth === 0) {
                throw ReaderException::forNode(
                    $node,
                    $root,
                    $node->getCodePrefix() . ' is not valid outside quasiquote',
                    errorCode: ErrorCode::READER_ERROR,
                );
            }

            --$this->quasiquoteDepth;
            try {
                return $this->readerFactory
                    ->createWrapReader($this)
                    ->read($node, $node->getTokenType() === Token::T_UNQUOTE ? Symbol::NAME_UNQUOTE : Symbol::NAME_UNQUOTE_SPLICING, $root);
            } finally {
                ++$this->quasiquoteDepth;
            }
        }

        if ($node->getTokenType() === Token::T_DEREF) {
            return $this->readerFactory
                ->createWrapReader($this)
                ->read($node, Symbol::NAME_DEREF, $root);
        }

        if ($node->getTokenType() === Token::T_VAR_QUOTE) {
            return $this->readerFactory
                ->createWrapReader($this)
                ->read($node, Symbol::NAME_VAR, $root);
        }

        if ($node->getTokenType() === Token::T_QUASIQUOTE) {
            ++$this->quasiquoteDepth;
            try {
                return $this->readerFactory
                    ->createQuoasiquoteReader($this, $this->quasiquoteTransformer)
                    ->read($node, $root);
            } finally {
                --$this->quasiquoteDepth;
            }
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
