<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\DefStructInterface;
use Phel\Compiler\Domain\Analyzer\Ast\DefStructMethod;
use Phel\Compiler\Domain\Analyzer\Ast\DefStructNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Emitter\OutputEmitter\MungeInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use ReflectionMethod;

use function count;

final readonly class DefStructSymbol implements SpecialFormAnalyzerInterface
{
    public function __construct(
        private AnalyzerInterface $analyzer,
        private MungeInterface $munge,
    ) {
    }

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): DefStructNode
    {
        if (count($list) < 3) {
            throw AnalyzerException::withLocation(
                "At least two arguments are required for 'defstruct. Got " . count($list),
                $list,
            );
        }

        $structSymbol = $list->get(1);
        if (!($structSymbol instanceof Symbol)) {
            throw AnalyzerException::withLocation("First argument of 'defstruct must be a Symbol.", $list);
        }

        $structParams = $list->get(2);
        if (!($structParams instanceof PersistentVectorInterface)) {
            throw AnalyzerException::withLocation("Second argument of 'defstruct must be a vector.", $list);
        }

        $params = $this->params($structParams);

        return new DefStructNode(
            $env,
            $this->analyzer->getNamespace(),
            $structSymbol,
            $params,
            $this->interfaces(
                $list->rest()->rest()->rest(),
                $env->withMergedLocals($params),
            ),
            $list->getStartLocation(),
        );
    }

    /**
     * @param PersistentVectorInterface<mixed> $vector
     *
     * @return list<Symbol>
     */
    private function params(PersistentVectorInterface $vector): array
    {
        $params = [];
        foreach ($vector as $element) {
            if (!($element instanceof Symbol)) {
                throw AnalyzerException::withLocation('Defstruct field elements must be Symbols.', $vector);
            }

            $params[] = $element;
        }

        return $params;
    }

    /**
     * @return list<DefStructInterface>
     */
    private function interfaces(PersistentListInterface $list, NodeEnvironmentInterface $env): array
    {
        if ($list->count() === 0) {
            return [];
        }

        $interfaces = [];
        for ($forms = $list; $forms != null; $forms = $forms->cdr()) {
            $first = $forms->first();

            if (!$first instanceof Symbol) {
                throw AnalyzerException::withLocation('Expected a interface name in defstruct', $list);
            }

            $classNode = $this->analyzer->resolve($first, $env);
            if (!$classNode instanceof PhpClassNameNode) {
                throw AnalyzerException::withLocation('Can not resolve interface ' . $first->getFullName(), $list);
            }

            $reflectionClass = $classNode->getReflectionClass();
            if (!$reflectionClass->isInterface()) {
                throw AnalyzerException::withLocation('Given interface ' . $first->getFullName() . ' is not an interface', $list);
            }

            $absoluteInterfaceName = $classNode->getAbsolutePhpName();
            $expectedMethods = $reflectionClass->getMethods();
            $expectedMethodIndex = [];
            foreach ($expectedMethods as $method) {
                $expectedMethodIndex[$method->getName()] = $method;
            }

            $methods = [];
            $countExpectedMethods = count($expectedMethods);
            for ($i = 0; $i < $countExpectedMethods; ++$i) {
                $forms = $forms->cdr();
                if (!$forms instanceof PersistentListInterface) {
                    throw AnalyzerException::withLocation('Missing method for interface ' . $absoluteInterfaceName . ' in defstruct', $list);
                }

                $method = $forms->first();
                if (!$method instanceof PersistentListInterface) {
                    throw AnalyzerException::withLocation('Missing method for interface ' . $absoluteInterfaceName . ' in defstruct', $list);
                }

                $methods[] = $this->analyzeInterfaceMethod($method, $env, $expectedMethodIndex);
            }

            if (count($methods) !== count($expectedMethods)) {
                throw AnalyzerException::withLocation('Missing method for interface ' . $absoluteInterfaceName . ' in defstruct', $list);
            }

            $interfaces[] = new DefStructInterface(
                $absoluteInterfaceName,
                $methods,
            );
        }

        return $interfaces;
    }

    /**
     * @param array<string, ReflectionMethod> $expectedMethodIndex
     */
    private function analyzeInterfaceMethod(
        PersistentListInterface $list,
        NodeEnvironmentInterface $env,
        array $expectedMethodIndex,
    ): DefStructMethod {
        $methodName = $list->get(0);
        $mungedMethodName = $this->munge->encode($methodName->getName());
        if (!$methodName instanceof Symbol) {
            throw AnalyzerException::withLocation('Method name must be a Symbol', $list);
        }

        if (!isset($expectedMethodIndex[$mungedMethodName])) {
            throw AnalyzerException::withLocation("The interface doesn't support this method: " . $methodName->getName(), $list);
        }

        $arguments = $list->get(1);
        if (!$arguments instanceof PersistentVectorInterface) {
            throw AnalyzerException::withLocation('Method arguments must be a vector', $list);
        }

        // Analyze arguments and body as (fn arguments (do body))
        $fnNode = $this->analyzer->analyze(
            TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create('fn'),
                $arguments->rest(), // remove the first argument. The first argument is bound to $this
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create('let'),
                    TypeFactory::getInstance()->persistentVectorFromArray([
                        $arguments->first(),
                        Symbol::createForNamespace('php', '$this'),
                    ]),
                    ...($list->rest()->rest()->toArray()),
                ]),
            ]),
            $env,
        );

        if (!$fnNode instanceof FnNode) {
            throw AnalyzerException::withLocation('Can not correctly analyse method body', $list);
        }

        return new DefStructMethod(
            $methodName,
            $fnNode,
        );
    }
}
