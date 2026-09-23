<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;

use function count;
use function is_int;

/**
 * The walk an `(assoc-in ds [k1 k2 …] v)` or `(update-in ds [k1 k2 …] f …)`
 * with a literal path compiles to (#3328).
 *
 * The runtime fns destructure the path with `[k & ks]` and recurse, one
 * `get` on the way down and one `assoc` on the way up per level. This makes
 * the same reads and writes in the same order: a missing or nil level
 * becomes `{}`, and the leaf of an `update-in` binds the current value
 * before calling `f`, so a by-reference `f` sees a variable.
 *
 * A map level is read with `offsetExists`/`offsetGet` and written with
 * `put`, and a vector level with an `int` key is read the same way and
 * written with `update`: exactly what `phel.core/get` and `phel.core/assoc`
 * do for them. Every other level (a transient, a list, a `BigInt` or
 * non-integer vector index, a scalar) goes to `phel.core/get` and
 * `phel.core/assoc`, so it answers or throws as the runtime call does.
 *
 * Do NOT rename: the emitter bakes this FQN into generated PHP, and cached
 * `.phel` artifacts keep calling it by that name.
 */
final class AssocIn
{
    private function __construct() {}

    /**
     * @param list<mixed> $keys
     */
    public static function path(mixed $ds, array $keys, mixed $value): mixed
    {
        // `[k & ks]` binds nil to `k` on an empty path, so the runtime
        // answers `(assoc ds nil v)`.
        $last = count($keys) - 1;
        if ($last < 0) {
            return self::assoc($ds, null, $value);
        }

        $levels = [];
        $level = $ds;
        for ($i = 0; $i < $last; ++$i) {
            $levels[] = $level;
            $key = $keys[$i];
            $level = ($level instanceof PersistentMapInterface
                ? ($level->offsetExists($key) ? $level->offsetGet($key) : null)
                : self::get($level, $key)) ?? TypeFactory::getInstance()->persistentMapFromArray();
        }

        $res = $level instanceof PersistentMapInterface
            ? $level->put($keys[$last], $value)
            : self::assoc($level, $keys[$last], $value);

        for ($i = $last - 1; $i >= 0; --$i) {
            $parent = $levels[$i];
            $res = $parent instanceof PersistentMapInterface
                ? $parent->put($keys[$i], $res)
                : self::assoc($parent, $keys[$i], $res);
        }

        return $res;
    }

    /**
     * @param list<mixed> $keys
     */
    public static function update(mixed $ds, array $keys, mixed $f, mixed ...$args): mixed
    {
        $last = count($keys) - 1;
        if ($last < 0) {
            $current = self::get($ds, null);

            return self::assoc($ds, null, $f($current, ...$args));
        }

        $levels = [];
        $level = $ds;
        for ($i = 0; $i < $last; ++$i) {
            $levels[] = $level;
            $key = $keys[$i];
            $level = ($level instanceof PersistentMapInterface
                ? ($level->offsetExists($key) ? $level->offsetGet($key) : null)
                : self::get($level, $key)) ?? TypeFactory::getInstance()->persistentMapFromArray();
        }

        $key = $keys[$last];
        if ($level instanceof PersistentMapInterface) {
            $current = $level->offsetExists($key) ? $level->offsetGet($key) : null;
            $res = $level->put($key, $f($current, ...$args));
        } else {
            $current = self::get($level, $key);
            $res = self::assoc($level, $key, $f($current, ...$args));
        }

        for ($i = $last - 1; $i >= 0; --$i) {
            $parent = $levels[$i];
            $res = $parent instanceof PersistentMapInterface
                ? $parent->put($keys[$i], $res)
                : self::assoc($parent, $keys[$i], $res);
        }

        return $res;
    }

    private static function get(mixed $ds, mixed $key): mixed
    {
        if ($ds instanceof PersistentVectorInterface && is_int($key)) {
            return $ds->offsetExists($key) ? $ds->offsetGet($key) : null;
        }

        /** @var callable(mixed, mixed): mixed $get */
        $get = Registry::readRoot('phel.core', 'get');

        return $get($ds, $key);
    }

    private static function assoc(mixed $ds, mixed $key, mixed $value): mixed
    {
        if ($ds instanceof PersistentVectorInterface && is_int($key)) {
            return $ds->update($key, $value);
        }

        /** @var callable(mixed, mixed, mixed): mixed $assoc */
        $assoc = Registry::readRoot('phel.core', 'assoc');

        return $assoc($ds, $key, $value);
    }
}
