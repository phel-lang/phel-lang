<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\ReifyNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

use function count;

/**
 * (reify* (method-name [this arg1] body) ...).
 *
 * Creates an anonymous object with named methods. Used by the `reify` macro
 * which handles protocol dispatch registration.
 */
final readonly class ReifySymbol implements SpecialFormAnalyzerInterface
{
    public function __construct(
        private MethodBodyAnalyzer $methodBodyAnalyzer,
    ) {}

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): ReifyNode
    {
        if (count($list) < 2) {
            throw AnalyzerException::withLocation(
                "At least one method is required for 'reify*",
                $list,
            );
        }

        $methods = [];
        $allUses = [];

        for ($forms = $list->rest(); $forms !== null; $forms = $forms->cdr()) {
            $methodSpec = $forms->first();
            if (!$methodSpec instanceof PersistentListInterface) {
                throw AnalyzerException::withLocation('Each reify* method must be a list', $list);
            }

            $method = $this->methodBodyAnalyzer->analyze($methodSpec, $env);
            $methods[] = $method;

            foreach ($method->getFnNode()->getUses() as $use) {
                $allUses[] = $use;
            }
        }

        return new ReifyNode(
            $env,
            $methods,
            $this->deduplicateUses($allUses),
            $list->getStartLocation(),
        );
    }

    /**
     * @param list<Symbol> $uses
     *
     * @return list<Symbol>
     */
    private function deduplicateUses(array $uses): array
    {
        $seen = [];
        $result = [];
        foreach ($uses as $use) {
            $name = $use->getName();
            if (!isset($seen[$name])) {
                $seen[$name] = true;
                $result[] = $use;
            }
        }

        return $result;
    }
}
