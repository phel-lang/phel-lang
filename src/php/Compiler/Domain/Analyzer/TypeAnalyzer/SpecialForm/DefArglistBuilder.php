<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\MultiFnNode;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;
use Phel\Shared\Printer\Printer;

use function array_map;
use function array_pop;
use function array_slice;
use function implode;

/**
 * Formats a def's `:arglists` metadata string from the analyzed fn node(s).
 * Single-arity renders one `[a b & rest]` vector; multi-arity wraps every
 * arity's vector in `( ... )`. `$skipFirst` drops leading macro implicit
 * params (`&form`/`&env`) so the published arglist reflects the user-visible
 * signature.
 *
 * @internal
 */
final readonly class DefArglistBuilder
{
    public function buildFnNodeArglist(FnNode $fnNode, int $skipFirst = 0): string
    {
        $sourceParams = $fnNode->getSourceParams();
        if ($sourceParams instanceof PersistentVectorInterface) {
            return Printer::readable()->print(Phel::vector(array_slice($sourceParams->toArray(), $skipFirst)));
        }

        return $this->formatParamsVector($fnNode->getParams(), $fnNode->isVariadic(), $skipFirst);
    }

    public function buildMultiFnNodeArglists(MultiFnNode $multiFnNode, int $skipFirst = 0): string
    {
        $vectors = [];
        foreach ($multiFnNode->getFnNodes() as $fnNode) {
            $vectors[] = $this->buildFnNodeArglist($fnNode, $skipFirst);
        }

        return '(' . implode(' ', $vectors) . ')';
    }

    /**
     * @param list<Symbol> $params
     */
    private function formatParamsVector(array $params, bool $isVariadic, int $skipFirst = 0): string
    {
        if ($skipFirst > 0) {
            $params = array_slice($params, $skipFirst);
        }

        $names = array_map(
            static fn(Symbol $s): string => $s->getName(),
            $params,
        );

        if ($isVariadic && $names !== []) {
            $restParam = array_pop($names);
            $names[] = '&';
            $names[] = $restParam;
        }

        return '[' . implode(' ', $names) . ']';
    }
}
