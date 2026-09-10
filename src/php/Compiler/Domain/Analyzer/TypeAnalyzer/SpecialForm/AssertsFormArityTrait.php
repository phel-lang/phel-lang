<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;

use function count;

/**
 * Checks a special form is long enough before its analyzer reads an argument
 * out of it.
 *
 * `PersistentListInterface::get()` throws past the end, and that exception is
 * about a list rather than about the form the user wrote: it reached them as
 * `[PHEL403] Index out of bounds`, a runtime code, raised inside
 * `PersistentList`, with no snippet and no caret (#3297).
 *
 * @internal
 */
trait AssertsFormArityTrait
{
    /**
     * @param PersistentListInterface<mixed> $list
     * @param int                            $arity the number of forms the whole call must have, the form's own name included
     * @param string                         $usage the shape to show the reader, e.g. `(php/aget array key)`
     *
     * @throws AnalyzerException
     */
    private function assertArityAtLeast(PersistentListInterface $list, int $arity, string $usage): void
    {
        if (count($list) < $arity) {
            throw AnalyzerException::wrongArity($list, $usage);
        }
    }
}
