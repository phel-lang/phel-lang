<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\DefStructMethod;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;

use function count;

/**
 * Analyzes a method spec of the form (method-name [this args...] body...).
 *
 * The first argument is bound to PHP's $this via a let binding.
 * Shared by DefStructSymbol (for interface methods) and ReifySymbol (for protocol methods).
 */
final readonly class MethodBodyAnalyzer
{
    public function __construct(
        private AnalyzerInterface $analyzer,
    ) {}

    /**
     * @param PersistentListInterface<mixed> $list
     */
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): DefStructMethod
    {
        $methodName = $this->extractMethodName($list);
        $arguments = $this->extractArguments($list);
        $fnNode = $this->analyzeBody($list, $arguments, $env);

        return new DefStructMethod($methodName, $fnNode);
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    private function extractMethodName(PersistentListInterface $list): Symbol
    {
        $methodName = $list->get(0);
        if (!$methodName instanceof Symbol) {
            throw AnalyzerException::wrongArgumentType('Method name', 'Symbol', $methodName, $list);
        }

        return $methodName;
    }

    /**
     * @param PersistentListInterface<mixed> $list
     *
     * @return PersistentVectorInterface<mixed>
     */
    private function extractArguments(PersistentListInterface $list): PersistentVectorInterface
    {
        $arguments = $list->get(1);
        if (!$arguments instanceof PersistentVectorInterface) {
            throw AnalyzerException::withLocation('Method arguments must be a vector', $list);
        }

        if (count($arguments) < 1) {
            throw AnalyzerException::withLocation(
                'Method must have at least one argument (this)',
                $list,
            );
        }

        return $arguments;
    }

    /**
     * @param PersistentListInterface<mixed>   $list
     * @param PersistentVectorInterface<mixed> $arguments
     */
    private function analyzeBody(
        PersistentListInterface $list,
        PersistentVectorInterface $arguments,
        NodeEnvironmentInterface $env,
    ): FnNode {
        /** @var PersistentVectorInterface<mixed> $argumentsRest */
        $argumentsRest = $arguments->rest();
        /** @var PersistentListInterface<mixed> $listRest1 */
        $listRest1 = $list->rest();
        /** @var PersistentListInterface<mixed> $listRest2 */
        $listRest2 = $listRest1->rest();
        $fnNode = $this->analyzer->analyze(
            Phel::list([
                Symbol::create('fn'),
                $argumentsRest,
                Phel::list([
                    Symbol::create('let'),
                    Phel::vector([
                        $arguments->first(),
                        Symbol::createForNamespace('php', '$this'),
                    ]),
                    ...$listRest2->toArray(),
                ]),
            ]),
            $env,
        );

        if (!$fnNode instanceof FnNode) {
            throw AnalyzerException::withLocation('Cannot correctly analyze method body', $list);
        }

        return $fnNode;
    }
}
