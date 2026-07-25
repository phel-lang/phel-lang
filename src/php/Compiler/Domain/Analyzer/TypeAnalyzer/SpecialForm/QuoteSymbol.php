<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\QuoteNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

use function count;

/**
 * (quote form) / 'form.
 *
 * Returns the form unevaluated.
 */
final class QuoteSymbol implements SpecialFormAnalyzerInterface
{
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): QuoteNode
    {
        $head = $list->get(0);
        if (!$head instanceof Symbol || $head->getName() !== Symbol::NAME_QUOTE) {
            throw AnalyzerException::withLocation("This is not a 'quote.", $list);
        }

        if (count($list) !== 2) {
            throw AnalyzerException::withLocation("Exactly one argument is required for 'quote", $list);
        }

        return new QuoteNode(
            $env,
            $list->get(1),
            $list->getStartLocation(),
        );
    }
}
