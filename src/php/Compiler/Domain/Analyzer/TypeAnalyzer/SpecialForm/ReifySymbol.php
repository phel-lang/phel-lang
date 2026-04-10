<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\DefStructMethod;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\ReifyNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
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
        private AnalyzerInterface $analyzer,
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

            $method = $this->analyzeMethod($methodSpec, $env);
            $methods[] = $method;

            foreach ($method->getFnNode()->getUses() as $use) {
                $allUses[] = $use;
            }
        }

        $uniqueUses = $this->deduplicateUses($allUses);

        return new ReifyNode(
            $env,
            $methods,
            $uniqueUses,
            $list->getStartLocation(),
        );
    }

    private function analyzeMethod(
        PersistentListInterface $list,
        NodeEnvironmentInterface $env,
    ): DefStructMethod {
        $methodName = $list->get(0);
        if (!$methodName instanceof Symbol) {
            throw AnalyzerException::wrongArgumentType('Method name', 'Symbol', $methodName, $list);
        }

        $arguments = $list->get(1);
        if (!$arguments instanceof PersistentVectorInterface) {
            throw AnalyzerException::withLocation('Method arguments must be a vector', $list);
        }

        if (count($arguments) < 1) {
            throw AnalyzerException::withLocation(
                "Method '" . $methodName->getName() . "' must have at least one argument (this)",
                $list,
            );
        }

        $fnNode = $this->analyzer->analyze(
            Phel::list([
                Symbol::create('fn'),
                $arguments->rest(),
                Phel::list([
                    Symbol::create('let'),
                    Phel::vector([
                        $arguments->first(),
                        Symbol::createForNamespace('php', '$this'),
                    ]),
                    ...($list->rest()->rest()->toArray()),
                ]),
            ]),
            $env,
        );

        if (!$fnNode instanceof FnNode) {
            throw AnalyzerException::withLocation('Cannot correctly analyze method body', $list);
        }

        return new DefStructMethod(
            $methodName,
            $fnNode,
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
