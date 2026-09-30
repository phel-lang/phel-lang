<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader\ExpressionReader;

use Phel;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Lang\BigDecimal;
use Phel\Lang\BigInt;
use Phel\Lang\Keyword;
use Phel\Lang\Ratio;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Printer\Printer;

use function count;
use function is_scalar;

/**
 * Rejects a map or set literal whose constant keys repeat. A key that is not
 * a constant, such as a symbol or a call, can differ at runtime, so it keeps
 * last-wins.
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
            if (!self::isConstant($key)) {
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

    private static function isConstant(mixed $key): bool
    {
        return $key === null
            || is_scalar($key)
            || $key instanceof Keyword
            || $key instanceof Ratio
            || $key instanceof BigInt
            || $key instanceof BigDecimal;
    }
}
