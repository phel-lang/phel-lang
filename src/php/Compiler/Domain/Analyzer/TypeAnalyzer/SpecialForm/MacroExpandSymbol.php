<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\MacroExpandNode;
use Phel\Compiler\Domain\Analyzer\Ast\QuoteNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

use function count;

final class MacroExpandSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): MacroExpandNode
    {
        if (!($list->get(0) instanceof Symbol && $list->get(0)->getName() === Symbol::NAME_MACRO_EXPAND)) {
            throw AnalyzerException::withLocation("This is not a 'macroexpand.", $list);
        }

        if (count($list) !== 2) {
            throw AnalyzerException::withLocation("Exactly one argument is required for 'macroexpand", $list);
        }

        $form = $list->get(1);
        if ($form instanceof PersistentListInterface) {
            if ($form->get(0) instanceof Symbol) {
                if ($form->get(0)->getName() === Symbol::NAME_QUOTE) {
                    $form = $form->rest()->first();
                }
            }
        }

        return new MacroExpandNode(
            $env,
            $this->analyzer->analyze($form, $env),
            $list->getStartLocation(),
        );
    }
}
