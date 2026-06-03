<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\PhpAttributeRenderer;

use function implode;
use function is_string;

/**
 * Shared reading of PHP-interop metadata off a Phel symbol: the `:tag` type
 * hint and the `:php/attr` attribute specs. Used by the emitters that generate
 * PHP classes/interfaces from Phel forms (`defstruct`, `definterface`).
 */
trait PhpAttributeEmitterTrait
{
    /**
     * Reads the PHP type hint from a symbol's `:tag` meta, mirroring the
     * convention used for typed function parameters. Returns null when absent.
     *
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    private function tagTypeFromMeta(?PersistentMapInterface $meta): ?string
    {
        if (!$meta instanceof PersistentMapInterface) {
            return null;
        }

        return $this->renderTag($meta->find(Keyword::create('tag')));
    }

    /**
     * Resolves a `:tag` value into a PHP type string. A bare symbol/string is
     * used verbatim (so `?int`, `self`, `\DateTime` pass through); a list is a
     * union (`(int string)` => `int|string`); a vector is an intersection
     * (`[Countable Stringable]` => `Countable&Stringable`).
     */
    private function renderTag(mixed $tag): ?string
    {
        if ($tag instanceof PersistentListInterface) {
            return $this->joinTagParts($tag, '|');
        }

        if ($tag instanceof PersistentVectorInterface) {
            return $this->joinTagParts($tag, '&');
        }

        if ($tag instanceof Symbol) {
            $tag = $tag->getName();
        }

        return is_string($tag) && $tag !== '' ? $tag : null;
    }

    /**
     * Joins the symbol/string members of a composite tag with `$separator`.
     *
     * @param iterable<mixed> $parts
     */
    private function joinTagParts(iterable $parts, string $separator): ?string
    {
        $names = [];
        foreach ($parts as $part) {
            if ($part instanceof Symbol) {
                $part = $part->getName();
            }

            if (is_string($part) && $part !== '') {
                $names[] = $part;
            }
        }

        return $names === [] ? null : implode($separator, $names);
    }

    /**
     * Renders the `:php/attr` specs carried by the symbol meta into PHP
     * attribute lines (`#[...]`), or an empty list when none are present.
     *
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     *
     * @return list<string>
     */
    private function phpAttributeLines(PhpAttributeRenderer $renderer, ?PersistentMapInterface $meta): array
    {
        if (!$meta instanceof PersistentMapInterface) {
            return [];
        }

        return $renderer->render($meta->find(Keyword::create('attr', 'php')));
    }
}
