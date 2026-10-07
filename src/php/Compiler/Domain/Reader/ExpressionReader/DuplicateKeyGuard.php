<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader\ExpressionReader;

use Phel;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Lang\BigDecimal;
use Phel\Lang\BigInt;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Ratio;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Printer\Printer;

use function count;
use function is_scalar;

/**
 * Rejects a map or set literal whose keys repeat. A constant, a symbol (the
 * same binding twice) and a vector, map or set built from those compare as
 * written. A key holding a call can differ at runtime, so it keeps last-wins.
 *
 * @internal
 */
final readonly class DuplicateKeyGuard
{
    /**
     * @param list<mixed>         $keys
     * @param list<NodeInterface> $keyNodes the node each key was read from, same order
     *
     * @throws ReaderException
     */
    public static function assertUnique(array $keys, array $keyNodes, int $distinctCount, NodeInterface $root): void
    {
        if ($distinctCount === count($keys)) {
            return;
        }

        $seen = Phel::set();
        foreach ($keys as $i => $key) {
            if (!self::isComparable($key)) {
                continue;
            }

            if ($seen->contains($key)) {
                throw ReaderException::forNode(
                    $keyNodes[$i],
                    $root,
                    'Duplicate key: ' . Printer::readable()->print($key),
                    null,
                    ErrorCode::DUPLICATE_KEY,
                );
            }

            $seen = $seen->add($key);
        }
    }

    private static function isComparable(mixed $key): bool
    {
        if ($key instanceof PersistentMapInterface) {
            foreach ($key as $k => $v) {
                if (!self::isComparable($k) || !self::isComparable($v)) {
                    return false;
                }
            }

            return true;
        }

        if ($key instanceof PersistentVectorInterface || $key instanceof PersistentHashSetInterface) {
            foreach ($key as $element) {
                if (!self::isComparable($element)) {
                    return false;
                }
            }

            return true;
        }

        return $key === null
            || is_scalar($key)
            || $key instanceof Symbol
            || $key instanceof Keyword
            || $key instanceof Ratio
            || $key instanceof BigInt
            || $key instanceof BigDecimal;
    }
}
