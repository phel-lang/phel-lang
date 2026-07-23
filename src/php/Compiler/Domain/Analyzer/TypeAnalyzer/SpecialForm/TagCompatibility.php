<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\TagResolver;

use function array_map;
use function explode;
use function in_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function str_contains;
use function str_starts_with;
use function substr;

/**
 * Lightweight literal-type probe + tag compatibility check used by the
 * static type checker. Recognizes only the cases the analyzer already
 * has direct AST evidence for (`LiteralNode`, `VectorNode`, `MapNode`,
 * `SetNode`); anything else returns `null`, which the caller treats
 * as "skip — no false positive".
 */
final class TagCompatibility
{
    /**
     * Reads the `:tag` keyword from a Symbol's metadata. Symbol values
     * (`^int x`) and string values (`^"int|null" x`) resolve to the
     * raw type string; anything else is `null`.
     */
    public static function extractParamTag(Symbol $param): ?string
    {
        return TagResolver::fromMeta($param->getMeta());
    }

    public static function literalTypeOf(AbstractNode $node): ?string
    {
        return match (true) {
            $node instanceof LiteralNode => self::scalarType($node->getValue()),
            $node instanceof VectorNode => 'vector',
            $node instanceof MapNode => 'map',
            $node instanceof SetNode => 'set',
            default => null,
        };
    }

    /**
     * Tail-position literal type. Walks `do` / `let` / `if` down to a
     * leaf; for `if`, only resolves when both branches agree on a
     * concrete type. Anything else returns `null` so the caller can
     * skip without false-positives.
     */
    public static function tailLiteralType(AbstractNode $node): ?string
    {
        if ($node instanceof DoNode) {
            return self::tailLiteralType($node->getRet());
        }

        if ($node instanceof LetNode) {
            return self::tailLiteralType($node->getBodyExpr());
        }

        if ($node instanceof IfNode) {
            $then = self::tailLiteralType($node->getThenExpr());
            $else = self::tailLiteralType($node->getElseExpr());
            if ($then === null || $else === null) {
                return null;
            }

            return $then === $else ? $then : null;
        }

        return self::literalTypeOf($node);
    }

    /**
     * `tag` is a raw PHP type expression as written by the user
     * (`int`, `?int`, `int|null`, `\Foo\Bar`). `literalType` is one of
     * the strings produced by `literalTypeOf`. Returns `false` only
     * when the literal definitively cannot satisfy the tag.
     */
    public static function accepts(string $tag, string $literalType): bool
    {
        $tag = trim($tag);
        if ($tag === '' || $tag === 'mixed') {
            return true;
        }

        if (str_starts_with($tag, '?')) {
            return $literalType === 'nil'
                || self::accepts(substr($tag, 1), $literalType);
        }

        if (str_contains($tag, '|')) {
            $parts = array_map(trim(...), explode('|', $tag));
            return array_any($parts, static fn(string $part): bool => self::accepts($part, $literalType));
        }

        if ($literalType === 'nil') {
            return $tag === 'null';
        }

        return self::scalarTagMatches($tag, $literalType);
    }

    private static function scalarType(mixed $value): ?string
    {
        return match (true) {
            $value === null => 'nil',
            is_bool($value) => 'bool',
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_string($value) => 'string',
            $value instanceof Keyword => 'keyword',
            default => null,
        };
    }

    private static function scalarTagMatches(string $tag, string $literalType): bool
    {
        return match ($tag) {
            'int' => $literalType === 'int',
            'float' => $literalType === 'float' || $literalType === 'int',
            'string' => $literalType === 'string',
            'bool' => $literalType === 'bool',
            'array' => $literalType === 'vector' || $literalType === 'map',
            'iterable' => in_array($literalType, ['vector', 'map', 'set'], true),
            // `null`/`void`/`never` cannot be satisfied by a concrete returned
            // value: `null` only holds nil (handled above), a `void` fn must not
            // return a value, and a `never` fn must not return at all, so any
            // non-nil tail literal is incompatible with them.
            'null', 'void', 'never' => false,
            default => true,
        };
    }
}
