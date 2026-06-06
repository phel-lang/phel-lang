<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\DefStructInterface;
use Phel\Compiler\Domain\Analyzer\Ast\DefStructMethod;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Shared\MungeInterface;
use ReflectionMethod;

use function count;
use function sprintf;

/**
 * Parses the inline-implementation tail shared by `defstruct` and `defenum`:
 * a sequence of interface symbols (each followed by impls for every method the
 * interface declares, validated against reflection) and `:php` blocks of bare
 * methods emitted directly on the generated class.
 */
final readonly class InterfaceImplementationsAnalyzer
{
    public function __construct(
        private AnalyzerInterface $analyzer,
        private MungeInterface $munge,
        private MethodBodyAnalyzer $methodBodyAnalyzer,
        private PhpBlockAnalyzer $phpBlockAnalyzer,
    ) {}

    /**
     * Whether the form is the `:php` block marker that opens a sequence of
     * bare methods. Lets a caller (e.g. `defenum`) tell where its leading
     * forms end and this implementations tail begins.
     */
    public function isPhpMarker(mixed $form): bool
    {
        return $this->phpBlockAnalyzer->isMarker($form);
    }

    /**
     * @param PersistentListInterface<mixed> $list
     *
     * @return list<DefStructInterface>
     */
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env, string $context): array
    {
        if ($list->count() === 0) {
            return [];
        }

        $interfaces = [];
        $forms = $list;
        for (; $forms !== null; $forms = $forms->cdr()) {
            $first = $forms->first();

            if ($this->phpBlockAnalyzer->isMarker($first)) {
                [$interfaces[], $forms] = $this->phpBlockAnalyzer->analyze($forms, $env, $context === 'defstruct');
                continue;
            }

            if (!$first instanceof Symbol) {
                throw AnalyzerException::withLocation(sprintf('Expected a interface name in %s', $context), $list);
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
                    throw AnalyzerException::withLocation('Missing method for interface ' . $absoluteInterfaceName . ' in ' . $context, $list);
                }

                $method = $forms->first();
                if (!$method instanceof PersistentListInterface) {
                    throw AnalyzerException::withLocation('Missing method for interface ' . $absoluteInterfaceName . ' in ' . $context, $list);
                }

                $methods[] = $this->analyzeInterfaceMethod($method, $env, $expectedMethodIndex);
            }

            if (count($methods) !== count($expectedMethods)) {
                throw AnalyzerException::withLocation('Missing method for interface ' . $absoluteInterfaceName . ' in ' . $context, $list);
            }

            $interfaces[] = new DefStructInterface(
                $absoluteInterfaceName,
                $methods,
            );
        }

        return $interfaces;
    }

    /**
     * @param PersistentListInterface<mixed>  $list
     * @param array<string, ReflectionMethod> $expectedMethodIndex
     */
    private function analyzeInterfaceMethod(
        PersistentListInterface $list,
        NodeEnvironmentInterface $env,
        array $expectedMethodIndex,
    ): DefStructMethod {
        $methodName = $list->get(0);
        if (!$methodName instanceof Symbol) {
            throw AnalyzerException::wrongArgumentType('Method name', 'Symbol', $methodName, $list);
        }

        $mungedMethodName = $this->munge->encode($methodName->getName());

        if (!isset($expectedMethodIndex[$mungedMethodName])) {
            throw AnalyzerException::withLocation("The interface doesn't support this method: " . $methodName->getName(), $list);
        }

        return $this->methodBodyAnalyzer->analyze($list, $env);
    }
}
