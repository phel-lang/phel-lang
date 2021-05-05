<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Analyzer\Ast\QuoteNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

final class QuoteSymbol implements SpecialFormAnalyzerInterface
{
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): QuoteNode
    {
        if (!($list->get(0) instanceof Symbol && $list->get(0)->getName() === Symbol::NAME_QUOTE)) {
            throw AnalyzerException::withLocation("This is not a 'quote.", $list);
        }

        if (count($list) !== 2) {
            throw AnalyzerException::withLocation("Exactly one argument is required for 'quote", $list);
        }

        return new QuoteNode(
            $env,
            $list->get(1),
            $list->getStartLocation()
        );
    }
}
