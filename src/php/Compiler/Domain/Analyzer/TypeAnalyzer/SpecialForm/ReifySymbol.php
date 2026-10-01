<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\DefStructMethod;
use Phel\Compiler\Domain\Analyzer\Ast\ReifyNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

use function count;

/**
 * (reify* (method-name [this arg1] body) ... Interface (method [this] body) ...).
 *
 * Creates an anonymous object with named methods. Leading methods stand on
 * their own; from the first symbol on, each PHP interface is followed by the
 * methods it declares, as in `defstruct`. Used by the `reify` macro, which
 * handles protocol dispatch registration.
 *
 * @internal
 */
final readonly class ReifySymbol implements SpecialFormAnalyzerInterface
{
    public function __construct(
        private MethodBodyAnalyzer $methodBodyAnalyzer,
        private InterfaceImplementationsAnalyzer $implementationsAnalyzer,
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
        $forms = $list->rest();
        for (; $forms !== null && !$forms->first() instanceof Symbol; $forms = $forms->cdr()) {
            $methodSpec = $forms->first();
            if (!$methodSpec instanceof PersistentListInterface) {
                throw AnalyzerException::withLocation('Each reify* method must be a list', $list);
            }

            $methods[] = $this->methodBodyAnalyzer->analyze($methodSpec, $env);
        }

        $interfaceNames = [];
        $interfaces = $forms instanceof PersistentListInterface
            ? $this->implementationsAnalyzer->analyze($forms, $env, 'reify')
            : [];
        foreach ($interfaces as $interface) {
            $interfaceNames[] = $interface->getAbsoluteInterfaceName();
            foreach ($interface->getMethods() as $method) {
                $methods[] = $method;
            }
        }

        return new ReifyNode(
            $env,
            $methods,
            $interfaceNames,
            $this->uses($methods),
            $list->getStartLocation(),
        );
    }

    /**
     * @param list<DefStructMethod> $methods
     *
     * @return list<Symbol>
     */
    private function uses(array $methods): array
    {
        $seen = [];
        $result = [];
        foreach ($methods as $method) {
            foreach ($method->getFnNode()->getUses() as $use) {
                $name = $use->getName();
                if (!isset($seen[$name])) {
                    $seen[$name] = true;
                    $result[] = $use;
                }
            }
        }

        return $result;
    }
}
