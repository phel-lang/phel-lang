<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader\ExpressionReader;

use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\StringNode;
use Phel\Compiler\Domain\Parser\ParserNode\TaggedLiteralNode;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;

use function preg_match;
use function sprintf;
use function strtolower;

/**
 * Reads Phel's built-in tagged literals (e.g. `#uuid`). Unknown tags are
 * rejected here so they can still be silently discarded when they sit inside
 * an unselected reader-conditional branch (that discard happens earlier, in
 * the parser).
 */
final class TaggedLiteralReader
{
    private const string UUID_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * @throws ReaderException
     */
    public function read(TaggedLiteralNode $node, NodeInterface $root): string
    {
        return match ($node->getTag()) {
            'uuid' => $this->readUuid($node, $root),
            default => throw ReaderException::forNode(
                $node,
                $root,
                sprintf(
                    "Unknown tagged literal '#%s'. Phel has no built-in handler for this tag; it may only appear inside a non-selected reader-conditional branch (e.g. :clj, :jank).",
                    $node->getTag(),
                ),
            ),
        };
    }

    /**
     * @throws ReaderException
     */
    private function readUuid(TaggedLiteralNode $node, NodeInterface $root): string
    {
        $form = $node->getForm();

        if (!$form instanceof StringNode) {
            throw ReaderException::forNode(
                $node,
                $root,
                '#uuid expects a string literal (e.g. #uuid "00000000-0000-0000-0000-000000000000").',
            );
        }

        $value = $form->getValue();

        if (preg_match(self::UUID_REGEX, $value) !== 1) {
            throw ReaderException::forNode(
                $node,
                $root,
                sprintf('#uuid value %s is not a canonical UUID string.', $form->getCode()),
            );
        }

        return strtolower($value);
    }
}
