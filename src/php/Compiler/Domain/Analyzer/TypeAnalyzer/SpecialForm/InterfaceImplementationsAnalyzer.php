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
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\MungeInterface;
use ReflectionMethod;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function class_exists;
use function count;
use function implode;
use function interface_exists;
use function sprintf;
use function strtolower;

/**
 * Parses the inline-implementation tail shared by `defstruct` and `defenum`:
 * a sequence of interface symbols (each followed by impls for every method the
 * interface declares, validated against reflection) and `:php` blocks of bare
 * methods emitted directly on the generated class.
 *
 * @internal
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
                throw AnalyzerException::withLocation(sprintf('Expected a interface name in %s', $context), $list, errorCode: ErrorCode::INTERFACE_ERROR);
            }

            $classNode = $this->analyzer->resolve($first, $env);
            if (!$classNode instanceof PhpClassNameNode) {
                throw AnalyzerException::withLocation('Can not resolve interface ' . $first->getFullName(), $list, errorCode: ErrorCode::INTERFACE_ERROR);
            }

            $absoluteInterfaceName = $classNode->getAbsolutePhpName();
            $expectedMethodIndex = $this->expectedMethodIndex($classNode, $first, $list);

            $methods = [];
            $countExpectedMethods = count($expectedMethodIndex);
            for ($i = 0; $i < $countExpectedMethods; ++$i) {
                $next = $forms->cdr();
                $method = $next?->first();
                if (!$next instanceof PersistentListInterface || !$method instanceof PersistentListInterface) {
                    $this->throwMissingMethods($absoluteInterfaceName, $expectedMethodIndex, $methods, $list, $context);
                }

                $forms = $next;
                $methods[] = $this->analyzeInterfaceMethod($method, $env, $absoluteInterfaceName, $expectedMethodIndex);
            }

            $interfaces[] = new DefStructInterface(
                $absoluteInterfaceName,
                $methods,
            );
        }

        $this->assertDistinctMethods(
            array_merge(...array_map(static fn(DefStructInterface $interface): array => $interface->getMethods(), $interfaces)),
            $list,
            $context,
        );

        return $interfaces;
    }

    /**
     * PHP rejects a class that declares a method twice, and the error only
     * surfaces when the generated class loads. A method shared by two listed
     * interfaces (a repeated interface, or a parent next to its child) has to
     * be written once, under the most specific interface.
     *
     * @param list<DefStructMethod>          $methods
     * @param PersistentListInterface<mixed> $list
     */
    public function assertDistinctMethods(array $methods, PersistentListInterface $list, string $context): void
    {
        $seen = [];
        foreach ($methods as $method) {
            $name = strtolower($method->getName()->getName());
            if (isset($seen[$name])) {
                throw AnalyzerException::withLocation(
                    sprintf(
                        '%s defines method %s more than once. List each method once, under the most specific interface or protocol.',
                        $context,
                        $method->getName()->getName(),
                    ),
                    $list,
                    errorCode: ErrorCode::INTERFACE_ERROR,
                );
            }

            $seen[$name] = true;
        }
    }

    /**
     * @param array<string, ?ReflectionMethod> $expectedMethodIndex
     * @param list<DefStructMethod>            $methods
     * @param PersistentListInterface<mixed>   $list
     */
    private function throwMissingMethods(
        string $absoluteInterfaceName,
        array $expectedMethodIndex,
        array $methods,
        PersistentListInterface $list,
        string $context,
    ): never {
        $implemented = array_map(fn(DefStructMethod $method): string => $this->munge->encode($method->getName()->getName()), $methods);
        $missing = array_diff(array_keys($expectedMethodIndex), $implemented);

        throw AnalyzerException::withLocation(
            sprintf('Missing method for interface %s in %s: %s', $absoluteInterfaceName, $context, implode(', ', $missing)),
            $list,
            errorCode: ErrorCode::INTERFACE_ERROR,
        );
    }

    /**
     * The munged method names the interface expects, each mapped to its
     * reflection (null when the interface is not loaded yet).
     *
     * Reflection is the source of truth, but it only works once the PHP
     * interface exists — which for a `definterface` in the very same file
     * means the form must already have been emitted AND evaluated. Static
     * analysis never evaluates, so fall back to what `definterface`
     * recorded in the global environment during this pass.
     *
     * @param PersistentListInterface<mixed> $list
     *
     * @return array<string, ?ReflectionMethod>
     */
    private function expectedMethodIndex(
        PhpClassNameNode $classNode,
        Symbol $interfaceSymbol,
        PersistentListInterface $list,
    ): array {
        $resolved = $classNode->getName();
        $namespace = $resolved->getNamespace();

        if (!class_exists($classNode->getAbsolutePhpName())
            && !interface_exists($classNode->getAbsolutePhpName())
        ) {
            $declared = $namespace === null
                ? null
                : $this->analyzer->getInterfaceMethods($namespace, $resolved);

            if ($declared === null) {
                throw AnalyzerException::withLocation(
                    'Can not resolve interface ' . $interfaceSymbol->getFullName(),
                    $list,
                    errorCode: ErrorCode::INTERFACE_ERROR,
                );
            }

            $index = [];
            foreach ($declared as $methodName) {
                $index[$this->munge->encode($methodName)] = null;
            }

            return $index;
        }

        $reflectionClass = $classNode->getReflectionClass();
        if (!$reflectionClass->isInterface()) {
            throw AnalyzerException::withLocation('Given interface ' . $interfaceSymbol->getFullName() . ' is not an interface', $list, errorCode: ErrorCode::INTERFACE_ERROR);
        }

        $index = [];
        foreach ($reflectionClass->getMethods() as $method) {
            $index[$method->getName()] = $method;
        }

        return $index;
    }

    /**
     * @param PersistentListInterface<mixed>   $list
     * @param array<string, ?ReflectionMethod> $expectedMethodIndex
     */
    private function analyzeInterfaceMethod(
        PersistentListInterface $list,
        NodeEnvironmentInterface $env,
        string $absoluteInterfaceName,
        array $expectedMethodIndex,
    ): DefStructMethod {
        $methodName = $list->get(0);
        if (!$methodName instanceof Symbol) {
            throw AnalyzerException::wrongArgumentType('Method name', 'Symbol', $methodName, $list);
        }

        $mungedMethodName = $this->munge->encode($methodName->getName());

        if (!array_key_exists($mungedMethodName, $expectedMethodIndex)) {
            throw AnalyzerException::withLocation("The interface doesn't support this method: " . $methodName->getName(), $list, errorCode: ErrorCode::INTERFACE_ERROR);
        }

        $expected = $expectedMethodIndex[$mungedMethodName];
        $method = $this->methodBodyAnalyzer->analyze(
            $list,
            $env,
            $expected instanceof ReflectionMethod ? InterfaceMethodReturnType::of($expected) : null,
        );

        if ($expected instanceof ReflectionMethod) {
            $this->assertCompatibleParameters($method, $expected, $absoluteInterfaceName, $list);
        }

        return $method;
    }

    /**
     * PHP rejects an implementation that requires more parameters than the
     * interface method, or accepts fewer, and only when the class loads.
     *
     * @param PersistentListInterface<mixed> $list
     */
    private function assertCompatibleParameters(
        DefStructMethod $method,
        ReflectionMethod $expected,
        string $absoluteInterfaceName,
        PersistentListInterface $list,
    ): void {
        $fnNode = $method->getFnNode();
        $required = $fnNode->getMinArity();
        if ($required <= $expected->getNumberOfRequiredParameters()
            && ($fnNode->isVariadic() || $required >= $expected->getNumberOfParameters())
        ) {
            return;
        }

        $expectedCount = $expected->getNumberOfParameters();
        throw AnalyzerException::withLocation(
            sprintf(
                'Method %s takes %d %s after this in interface %s, but this implementation takes %d',
                $method->getName()->getName(),
                $expectedCount,
                $expectedCount === 1 ? 'parameter' : 'parameters',
                $absoluteInterfaceName,
                $required,
            ),
            $list,
            errorCode: ErrorCode::INTERFACE_ERROR,
        );
    }
}
