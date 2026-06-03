<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpRefNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

use function count;

/**
 * (php/ref local).
 *
 * Marks a local variable as passed by reference inside a PHP interop call.
 * Only valid in argument position of `php/->`/`php/::`; the inner form must be
 * a local binding so the emitted closure can capture it with `use(&$local)`.
 */
final class PhpRefSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    /**
     * @param PersistentListInterface<mixed> $list
     */
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): PhpRefNode
    {
        if (count($list) !== 2) {
            throw AnalyzerException::withLocation("Exactly one argument is required for 'php/ref", $list);
        }

        $symbol = $list->get(1);
        if (!$symbol instanceof Symbol) {
            throw AnalyzerException::wrongArgumentType("First argument of 'php/ref", 'Symbol', $symbol, $list);
        }

        $resolved = $this->analyzer->analyze($symbol, $env->withExpressionContext());
        if (!$resolved instanceof LocalVarNode) {
            throw AnalyzerException::withLocation("'php/ref expects a local variable", $list);
        }

        return new PhpRefNode($env, $resolved, $list->getStartLocation());
    }
}
