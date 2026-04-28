<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader\ExpressionReader;

use Phel;
use Phel\Compiler\Application\Reader;
use Phel\Compiler\Domain\Lexer\Token;
use Phel\Compiler\Domain\Parser\ParserNode\ListNode;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\TaggedLiteralNode;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TagHandlerException;
use Phel\Lang\TagHandlers\BuiltinTagHandlers;
use Phel\Lang\TagRegistry;
use Throwable;

use function implode;
use function sprintf;

/**
 * Dispatches tagged literals (e.g. `#uuid`, `#inst`, `#regex`, `#php`).
 *
 * `#php` stays hard-wired here because it inspects the token type of the
 * following form (vector/map) before reading. Every other tag is resolved
 * via the global `TagRegistry`, which hosts both the built-in handlers
 * (`#uuid`, `#inst`, `#regex`) and any user-registered handlers added at
 * runtime through `(register-tag ...)`.
 */
final readonly class TaggedLiteralReader
{
    public function __construct(private Reader $reader) {}

    /**
     * @throws ReaderException
     */
    public function read(TaggedLiteralNode $node, NodeInterface $root): mixed
    {
        $tag = $node->getTag();

        if ($tag === 'php') {
            return $this->readPhp($node, $root);
        }

        $registry = TagRegistry::getInstance();
        $handler = $registry->get($tag);

        if ($handler === null) {
            throw ReaderException::forNode($node, $root, $this->unknownTagMessage($tag, $registry));
        }

        $formValue = $this->reader->readExpression($node->getForm(), $root);

        try {
            return $handler($formValue);
        } catch (TagHandlerException $e) {
            throw ReaderException::forNode($node, $root, $e->getMessage());
        } catch (Throwable $e) {
            throw ReaderException::forNode($node, $root, sprintf(
                "Tagged-literal handler for '#%s' threw an error: %s",
                $tag,
                $e->getMessage(),
            ));
        }
    }

    private function unknownTagMessage(string $tag, TagRegistry $registry): string
    {
        $list = '#' . implode(', #', $registry->allTags(BuiltinTagHandlers::RESERVED));

        return sprintf(
            "Unknown tagged literal '#%s'. Registered tags: %s. Use `(register-tag \"%s\" f)` to register a handler, or ensure the literal only appears inside a non-selected reader-conditional branch (e.g. :clj, :jank).",
            $tag,
            $list,
            $tag,
        );
    }

    /**
     * @throws ReaderException
     */
    private function readPhp(TaggedLiteralNode $node, NodeInterface $root): PersistentListInterface
    {
        $form = $node->getForm();

        if (!$form instanceof ListNode) {
            throw ReaderException::forNode(
                $node,
                $root,
                '#php expects a vector literal [..] or a map literal {..}.',
            );
        }

        $tokenType = $form->getTokenType();
        if ($tokenType === Token::T_OPEN_BRACKET) {
            return $this->buildCall($node, 'php-indexed-array', $form);
        }

        if ($tokenType === Token::T_OPEN_BRACE) {
            return $this->buildCall($node, 'php-associative-array', $form);
        }

        throw ReaderException::forNode(
            $node,
            $root,
            '#php expects a vector literal [..] or a map literal {..}.',
        );
    }

    /**
     * @throws ReaderException
     */
    private function buildCall(TaggedLiteralNode $node, string $fnName, ListNode $form): PersistentListInterface
    {
        $elements = [Symbol::create($fnName)->setStartLocation($node->getStartLocation())];

        foreach ($form->getChildren() as $child) {
            if ($child instanceof TriviaNodeInterface) {
                continue;
            }

            $elements[] = $this->reader->readExpression($child, $node);
        }

        return Phel::list($elements)
            ->setStartLocation($node->getStartLocation())
            ->setEndLocation($node->getEndLocation());
    }
}
