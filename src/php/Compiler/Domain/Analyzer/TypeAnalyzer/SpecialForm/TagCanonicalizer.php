<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\TagResolver;

use function count;
use function is_string;

/**
 * Rewrites a declared `:tag` so a bare name the current namespace imported
 * with `:use` becomes the rooted class it names (`^Moment` under
 * `(:use DateTimeImmutable :as Moment)` becomes `\DateTimeImmutable`).
 *
 * It runs where a tag is declared (fn params and return tag, `let`/`loop`
 * bindings, `def` meta, `foreach`, `defstruct` fields, `definterface`
 * members), while the owning namespace is the analyser's current one. The
 * rewritten tag is what the AST and the definition's meta carry, so a reader
 * in another namespace, an inlined body or a later compilation sees the
 * class the definition meant, never whatever its own `:use` table says.
 *
 * A tag with no imported member comes back as the same object, so an
 * untagged or scalar-tagged form costs one meta lookup.
 *
 * @internal
 */
final class TagCanonicalizer
{
    public static function symbol(Symbol $symbol, AnalyzerInterface $analyzer): Symbol
    {
        $meta = $symbol->getMeta();
        if (!$meta instanceof PersistentMapInterface) {
            return $symbol;
        }

        $canonical = self::meta($meta, $analyzer);

        return $canonical === $meta ? $symbol : $symbol->withMeta($canonical);
    }

    /**
     * The params' tags and the vector's own (return) tag.
     *
     * @param PersistentVectorInterface<mixed> $params
     *
     * @return PersistentVectorInterface<mixed>
     */
    public static function paramVector(PersistentVectorInterface $params, AnalyzerInterface $analyzer): PersistentVectorInterface
    {
        $result = $params;
        for ($i = 0, $n = count($params); $i < $n; ++$i) {
            $param = $params->get($i);
            if ($param instanceof Symbol) {
                $canonical = self::symbol($param, $analyzer);
                if ($canonical !== $param) {
                    $result = $result->update($i, $canonical);
                }
            }
        }

        $meta = $params->getMeta();
        $canonicalMeta = $meta instanceof PersistentMapInterface ? self::meta($meta, $analyzer) : null;
        if ($result === $params && $canonicalMeta === $meta) {
            return $params;
        }

        return $result->withMeta($canonicalMeta)->copyLocationFrom($params);
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $meta
     *
     * @return PersistentMapInterface<mixed, mixed>
     */
    public static function meta(PersistentMapInterface $meta, AnalyzerInterface $analyzer): PersistentMapInterface
    {
        $key = Keyword::create('tag');
        $tag = $meta->find($key);
        $canonical = self::tag($tag, $analyzer);

        return $canonical === $tag ? $meta : $meta->put($key, $canonical);
    }

    /**
     * A symbol tag stays a symbol and a string tag a string, so the meta
     * keeps its shape. A composite (list or vector) tag is left alone.
     */
    private static function tag(mixed $tag, AnalyzerInterface $analyzer): mixed
    {
        $name = $tag instanceof Symbol ? $tag->getName() : $tag;
        if (!is_string($name) || $name === '') {
            return $tag;
        }

        $expanded = TagResolver::expandImports($name, self::imports($analyzer));
        if ($expanded === $name) {
            return $tag;
        }

        return $tag instanceof Symbol
            ? Symbol::create($expanded)->copyLocationFrom($tag)
            : $expanded;
    }

    /**
     * @return array<string, string>
     */
    private static function imports(AnalyzerInterface $analyzer): array
    {
        $imports = [];
        foreach ($analyzer->getUseAliases($analyzer->getNamespace()) as $alias => $fullName) {
            $imports[$alias] = $fullName->getName();
        }

        return $imports;
    }
}
