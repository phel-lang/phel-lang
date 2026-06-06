<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\DefInterfaceMethod;
use Phel\Compiler\Domain\Analyzer\Ast\DefInterfaceNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassConst;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;

use function count;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * (definterface Name (method [args])).
 *
 * Defines a PHP interface implementable by structs.
 */
final class DefInterfaceSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    /**
     * @param PersistentListInterface<mixed> $list
     */
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): DefInterfaceNode
    {
        $interfaceSymbol = $list->get(1);
        if (!($interfaceSymbol instanceof Symbol)) {
            throw AnalyzerException::wrongArgumentType("First argument of 'definterface", 'Symbol', $interfaceSymbol, $list);
        }

        $this->analyzer->addInterface($this->analyzer->getNamespace(), $interfaceSymbol);

        /** @var PersistentListInterface<mixed> $rest */
        $rest = $list->rest();
        /** @var PersistentListInterface<mixed>|null $bodyList */
        $bodyList = $rest->cdr();

        [$methods, $consts] = $this->methodsAndConsts($bodyList);

        return new DefInterfaceNode(
            $env,
            $this->analyzer->getNamespace(),
            $interfaceSymbol,
            $methods,
            $consts,
            $list->getStartLocation(),
        );
    }

    /**
     * Methods are lists `(name [args])`. A `:php/const` marker switches the
     * remaining `(NAME value)` lists into typed class constants.
     *
     * @param PersistentListInterface<mixed>|null $list
     *
     * @return array{0: list<DefInterfaceMethod>, 1: list<PhpClassConst>}
     */
    private function methodsAndConsts(?PersistentListInterface $list): array
    {
        if (!$list instanceof PersistentListInterface) {
            return [[], []];
        }

        $methods = [];
        $consts = [];
        $inConstBlock = false;
        for ($forms = $list; $forms instanceof PersistentListInterface; $forms = $forms->cdr()) {
            $first = $forms->first();

            if ($this->isConstMarker($first)) {
                $inConstBlock = true;
                continue;
            }

            if (!$first instanceof PersistentListInterface) {
                throw AnalyzerException::withLocation('Methods in definterface must be lists', $list);
            }

            if ($inConstBlock) {
                $consts[] = $this->createConst($first);
            } else {
                $methods[] = $this->createDefInterfaceMethod($first);
            }
        }

        return [$methods, $consts];
    }

    private function isConstMarker(mixed $form): bool
    {
        return $form instanceof Keyword
            && $form->getNamespace() === 'php'
            && $form->getName() === 'const';
    }

    /**
     * @param PersistentListInterface<mixed> $const
     */
    private function createConst(PersistentListInterface $const): PhpClassConst
    {
        $name = $const->get(0);
        if (!$name instanceof Symbol) {
            throw AnalyzerException::withLocation('A :php/const name must be a symbol', $const);
        }

        if (count($const) !== 2) {
            throw AnalyzerException::withLocation('A :php/const must be (NAME value)', $const);
        }

        $value = $const->get(1);
        if (!is_int($value) && !is_float($value) && !is_string($value) && !is_bool($value) && $value !== null) {
            throw AnalyzerException::withLocation('A :php/const value must be an int, float, string, bool or nil', $const);
        }

        return new PhpClassConst($name, $value);
    }

    /**
     * @param PersistentListInterface<mixed> $method
     */
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

        if (count($method) > 2 && !is_string($method->get(2))) {
            throw AnalyzerException::withLocation('Method comments must be strings', $method);
        }

        return new DefInterfaceMethod(
            $name,
            $arguments->toArray(),
        );
    }
}
