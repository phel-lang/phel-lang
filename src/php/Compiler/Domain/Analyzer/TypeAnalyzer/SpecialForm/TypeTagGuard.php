<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Closure;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\PhpClassLike;
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
use function array_pop;
use function ctype_lower;
use function in_array;
use function is_string;
use function ltrim;
use function preg_split;
use function sprintf;
use function str_contains;
use function trim;

/**
 * Only a bare, lower-case-first name is judged: a capitalised or qualified
 * class may load after this file compiles. A bare name the current namespace
 * declares as a type resolves against the generated file's own namespace.
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
     * The structs being analysed: their constructor is defined only after
     * their fields and methods, which may name them.
     *
     * @var list<string>
     */
    private static array $declaring = [];

    /**
     * @template TResult
     *
     * @param Closure(): TResult $analyze
     *
     * @return TResult
     */
    public static function whileDeclaring(Symbol $type, Closure $analyze): mixed
    {
        self::$declaring[] = $type->getName();
        try {
            return $analyze();
        } finally {
            array_pop(self::$declaring);
        }
    }

    /**
     * The fn emitter drops a union or intersection tag, so only a single
     * type is judged here.
     *
     * @param PersistentVectorInterface<mixed> $params
     */
    public static function assertParamVector(PersistentVectorInterface $params, AnalyzerInterface $analyzer): void
    {
        foreach ($params as $param) {
            if ($param instanceof Symbol) {
                self::assertSingleTypeMeta($param->getMeta(), $param, $analyzer);
            }
        }

        self::assertSingleTypeMeta($params->getMeta(), $params, $analyzer);
    }

    public static function assertSymbol(Symbol $symbol, AnalyzerInterface $analyzer): void
    {
        $meta = $symbol->getMeta();
        if ($meta instanceof PersistentMapInterface) {
            self::assertTag($meta->find(Keyword::create('tag')), $symbol, $analyzer);
        }
    }

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    private static function assertSingleTypeMeta(?PersistentMapInterface $meta, TypeInterface $owner, AnalyzerInterface $analyzer): void
    {
        if (!$meta instanceof PersistentMapInterface) {
            return;
        }

        $tag = $meta->find(Keyword::create('tag'));
        if (!$tag instanceof PersistentListInterface && !$tag instanceof PersistentVectorInterface) {
            self::assertTag($tag, $owner, $analyzer);
        }
    }

    private static function assertTag(mixed $tag, TypeInterface $owner, AnalyzerInterface $analyzer): void
    {
        if ($tag instanceof PersistentListInterface || $tag instanceof PersistentVectorInterface) {
            foreach ($tag as $member) {
                self::assertTag($member, $owner, $analyzer);
            }

            return;
        }

        $name = $tag instanceof Symbol ? $tag->getName() : $tag;
        if (!is_string($name)) {
            return;
        }

        foreach (preg_split('/[|&]/', $name) ?: [] as $part) {
            $type = ltrim(trim($part, " ()\t"), '?');
            if (self::isUnresolvable($type, $analyzer)) {
                $at = $tag instanceof Symbol && $tag->getStartLocation() instanceof SourceLocation ? $tag : $owner;
                throw AnalyzerException::withLocation(self::message($type), $at, errorCode: ErrorCode::UNDEFINED_SYMBOL);
            }
        }
    }

    private static function isUnresolvable(string $type, AnalyzerInterface $analyzer): bool
    {
        return $type !== ''
            && ctype_lower($type[0])
            && !str_contains($type, '\\')
            && !str_contains($type, '.')
            && !in_array($type, self::PHP_TYPES, true)
            && !isset(TagResolver::TYPE_ALIASES[$type])
            && !PhpClassLike::exists($type)
            && !self::isDeclaredType($type, $analyzer);
    }

    private static function isDeclaredType(string $type, AnalyzerInterface $analyzer): bool
    {
        $ns = $analyzer->getNamespace();

        return in_array($type, self::$declaring, true)
            || isset($analyzer->getInterfaces($ns)[$type])
            || $analyzer->hasDefinition($ns, Symbol::create($type));
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
