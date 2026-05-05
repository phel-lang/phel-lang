<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\VarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

use function count;
use function sprintf;

/**
 * (var sym).
 *
 * Resolves `sym` against the current namespace, require aliases, and refers,
 * then yields a `VarNode` that emits as a runtime call to
 * `Registry::getInstance()->getVar($ns, $name)`. Throws if `sym` does not
 * resolve to a known global definition.
 */
final readonly class VarSymbol implements SpecialFormAnalyzerInterface
{
    public function __construct(
        private AnalyzerInterface $analyzer,
    ) {}

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): VarNode
    {
        if (count($list) !== 2) {
            throw AnalyzerException::withLocation("Exactly one argument is required for 'var", $list);
        }

        $arg = $list->get(1);
        if (!$arg instanceof Symbol) {
            throw AnalyzerException::withLocation("'var expects a symbol", $list);
        }

        $resolved = $this->analyzer->resolve($arg, $env);
        if (!$resolved instanceof GlobalVarNode) {
            throw AnalyzerException::withLocation(
                sprintf("Cannot resolve '%s' to a var: no global definition with that name", $arg->getFullName()),
                $list,
            );
        }

        return new VarNode(
            $env,
            $resolved->getNamespace(),
            $resolved->getName()->getName(),
            $list->getStartLocation(),
        );
    }
}
