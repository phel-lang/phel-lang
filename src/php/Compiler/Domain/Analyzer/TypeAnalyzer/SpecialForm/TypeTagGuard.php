<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\SymbolSuggestionProvider;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\TagResolver;

use function array_keys;
use function array_merge;
use function class_exists;
use function ctype_lower;
use function in_array;
use function interface_exists;
use function is_string;
use function ltrim;
use function preg_split;
use function sprintf;
use function str_contains;
use function trim;

/**
 * Only a bare, lower-case-first name is judged: a capitalised or qualified
 * class may load after this file compiles.
 *
 * @internal
 */
final class TypeTagGuard
{
    private const array PHP_TYPES = [
        'int', 'float', 'string', 'bool', 'array', 'object', 'iterable', 'callable',
        'mixed', 'void', 'never', 'null', 'false', 'true', 'self', 'static', 'parent',
    ];

    private const array CLOJURE_PRIMITIVES = [
        'long' => 'int',
        'integer' => 'int',
        'double' => 'float',
        'boolean' => 'bool',
    ];

    /**
     * @param PersistentVectorInterface<mixed> $params
     */
    public static function assertParamVector(PersistentVectorInterface $params): void
    {
        foreach ($params as $param) {
            if ($param instanceof Symbol) {
                self::assertMeta($param->getMeta(), $param);
            }
        }

        self::assertMeta($params->getMeta(), $params);
    }

    public static function assertSymbol(Symbol $symbol): void
    {
        self::assertMeta($symbol->getMeta(), $symbol);
    }

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    private static function assertMeta(?PersistentMapInterface $meta, TypeInterface $owner): void
    {
        if ($meta instanceof PersistentMapInterface) {
            self::assertTag($meta->find(Keyword::create('tag')), $owner);
        }
    }

    private static function assertTag(mixed $tag, TypeInterface $owner): void
    {
        if ($tag instanceof PersistentListInterface || $tag instanceof PersistentVectorInterface) {
            foreach ($tag as $member) {
                self::assertTag($member, $owner);
            }

            return;
        }

        $name = $tag instanceof Symbol ? $tag->getName() : $tag;
        if (!is_string($name)) {
            return;
        }

        foreach (preg_split('/[|&]/', $name) ?: [] as $part) {
            $type = ltrim(trim($part, " ()\t"), '?');
            if (self::isUnresolvable($type)) {
                $at = $tag instanceof Symbol && $tag->getStartLocation() instanceof SourceLocation ? $tag : $owner;
                throw AnalyzerException::withLocation(self::message($type), $at, errorCode: ErrorCode::UNDEFINED_SYMBOL);
            }
        }
    }

    private static function isUnresolvable(string $type): bool
    {
        return $type !== ''
            && ctype_lower($type[0])
            && !str_contains($type, '\\')
            && !str_contains($type, '.')
            && !in_array($type, self::PHP_TYPES, true)
            && !isset(TagResolver::TYPE_ALIASES[$type])
            && !class_exists($type)
            && !interface_exists($type);
    }

    private static function message(string $type): string
    {
        $suggestion = self::CLOJURE_PRIMITIVES[$type] ?? new SymbolSuggestionProvider()->findSimilar(
            $type,
            array_merge(self::PHP_TYPES, array_keys(TagResolver::TYPE_ALIASES)),
        )[0] ?? null;

        $message = sprintf('Unable to resolve classname: %s', $type);

        return $suggestion === null ? $message : sprintf("%s. Did you mean '%s'?", $message, $suggestion);
    }
}
