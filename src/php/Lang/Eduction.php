<?php

declare(strict_types=1);

namespace Phel\Lang;

use Generator;
use IteratorAggregate;

use function array_shift;
use function count;

/**
 * Holds a transducer plus a source collection. Each iteration applies the
 * transducer freshly to the source, yielding transformed values. No caching:
 * a second iteration re-runs the transducer chain from scratch.
 *
 * Constructed by phel.core/eduction.
 *
 * @implements IteratorAggregate<int, mixed>
 */
final class Eduction implements IteratorAggregate
{
    /** @var callable(callable(mixed...): mixed): (callable(mixed...): mixed) */
    private $xform;

    /**
     * @param callable(callable(mixed...): mixed): (callable(mixed...): mixed) $xform transducer:
     *                                                                                takes the step fn and returns the wrapped step fn, which is then called with
     *                                                                                0, 1 or 2 arguments (init / completion / accumulation arities)
     * @param iterable<mixed>                                                  $coll
     */
    public function __construct(
        callable $xform,
        private readonly iterable $coll,
    ) {
        $this->xform = $xform;
    }

    public function getIterator(): Generator
    {
        $buffer = [];
        $step = ($this->xform)(static function (mixed ...$args) use (&$buffer): mixed {
            $count = count($args);
            if ($count === 0) {
                return null;
            }

            if ($count === 1) {
                return $args[0];
            }

            $buffer[] = $args[1];
            return $args[0];
        });

        $acc = null;
        foreach ($this->coll as $x) {
            $acc = $step($acc, $x);
            $stop = $acc instanceof Reduced;
            if ($stop) {
                $acc = $acc->deref();
            }

            yield from $this->drain($buffer);
            if ($stop) {
                $step($acc);
                yield from $this->drain($buffer);
                return;
            }
        }

        $step($acc);
        yield from $this->drain($buffer);
    }

    /**
     * @param list<mixed> $buffer
     */
    private function drain(array &$buffer): Generator
    {
        while ($buffer !== []) {
            yield array_shift($buffer);
        }
    }
}
