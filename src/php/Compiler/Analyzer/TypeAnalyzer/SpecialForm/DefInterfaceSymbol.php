<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Analyzer\Ast\DefInterfaceMethod;
use Phel\Compiler\Analyzer\Ast\DefInterfaceNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;

final class DefInterfaceSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): DefInterfaceNode
    {
        $interfaceSymbol = $list->get(1);
        if (!($interfaceSymbol instanceof Symbol)) {
            throw AnalyzerException::withLocation("First argument of 'definterace must be a Symbol.", $list);
        }
        $this->analyzer->addInterface($this->analyzer->getNamespace(), $interfaceSymbol);

        return new DefInterfaceNode(
            $env,
            $this->analyzer->getNamespace(),
            $interfaceSymbol,
            $this->methods($list->rest()->cdr()),
            $list->getStartLocation()
        );
    }

    /**
     * @return list<DefInterfaceMethod>
     */
    private function methods(?PersistentListInterface $list): array
    {
        if ($list === null) {
            return [];
        }

        $methods = [];
        for ($forms = $list; $forms !== null; $forms = $forms->cdr()) {
            $first = $forms->first();

            if (!$first instanceof PersistentListInterface) {
                throw AnalyzerException::withLocation('Methods in definterface must be lists', $list);
            }

            $methods[] = $this->createDefInterfaceMethod($first);
        }

        return $methods;
    }

    private function createDefInterfaceMethod(PersistentListInterface $method): DefInterfaceMethod
    {
        $name = $method->get(0);
        if (!$name instanceof Symbol) {
            throw AnalyzerException::withLocation('Method names must be symbols', $method);
        }

        $arguments = $method->get(1);
        if (!$arguments instanceof PersistentVectorInterface) {
            throw AnalyzerException::withLocation('Method arguments must be vectors', $method);
        }

        foreach ($arguments as $argument) {
            if (!$argument instanceof Symbol) {
                throw AnalyzerException::withLocation('A method argument must be symbol', $arguments);
            }
        }

        $comment = null;
        if (count($method) > 2) {
            $comment = $method->get(2);
            if (!is_string($comment)) {
                throw AnalyzerException::withLocation('Method comments must be strings', $method);
            }
        }

        return new DefInterfaceMethod(
            $name,
            $arguments->toArray(),
            $comment
        );
    }
}
