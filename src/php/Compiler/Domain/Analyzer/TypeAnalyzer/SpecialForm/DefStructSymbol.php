<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\DefStructInterface;
use Phel\Compiler\Domain\Analyzer\Ast\DefStructMethod;
use Phel\Compiler\Domain\Analyzer\Ast\DefStructNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\MungeInterface;
use ReflectionMethod;

use function count;

/**
 * (defstruct Name [fields...]).
 *
 * Defines a struct type with named fields and a positional constructor.
 */
final readonly class DefStructSymbol implements SpecialFormAnalyzerInterface
{
    public function __construct(
        private AnalyzerInterface $analyzer,
        private MungeInterface $munge,
        private MethodBodyAnalyzer $methodBodyAnalyzer,
    ) {}

    /**
     * @param PersistentListInterface<mixed> $list
     */
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
            throw AnalyzerException::wrongArgumentType("First argument of 'defstruct", 'Symbol', $structSymbol, $list);
        }

        $structParams = $list->get(2);
        if (!($structParams instanceof PersistentVectorInterface)) {
            throw AnalyzerException::wrongArgumentType("Second argument of 'defstruct", 'Vector', $structParams, $list);
        }

        $params = $this->params($structParams);

        /** @var PersistentListInterface<mixed> $rest1 */
        $rest1 = $list->rest();
        /** @var PersistentListInterface<mixed> $rest2 */
        $rest2 = $rest1->rest();
        /** @var PersistentListInterface<mixed> $rest3 */
        $rest3 = $rest2->rest();

        return new DefStructNode(
            $env,
            $this->analyzer->getNamespace(),
            $structSymbol,
            $params,
            $this->interfaces(
                $rest3,
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
     * @param PersistentListInterface<mixed> $list
     *
     * @return list<DefStructInterface>
     */
    private function interfaces(PersistentListInterface $list, NodeEnvironmentInterface $env): array
    {
        if ($list->count() === 0) {
            return [];
        }

        $interfaces = [];
        $forms = $list;
        for (; $forms !== null; $forms = $forms->cdr()) {
            $first = $forms->first();

            if ($this->isPhpBlockMarker($first)) {
                [$interfaces[], $forms] = $this->phpBlock($forms, $env);
                continue;
            }

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
     * The `:php` marker opens a block of bare PHP methods that are emitted
     * directly on the struct class without a backing interface. This is the
     * only way to declare PHP magic methods (`__invoke`, `__toString`,
     * `__get`, ...) that belong to no PHP interface.
     */
    private function isPhpBlockMarker(mixed $form): bool
    {
        return $form instanceof Keyword
            && $form->getNamespace() === null
            && $form->getName() === 'php';
    }

    /**
     * Consumes every consecutive method form following the `:php` marker and
     * returns the bare-method interface together with the cursor pointing at
     * the last consumed form, so the caller's `cdr()` step resumes correctly.
     *
     * @param PersistentListInterface<mixed> $forms cursor positioned on `:php`
     *
     * @return array{0: DefStructInterface, 1: PersistentListInterface<mixed>}
     */
    private function phpBlock(PersistentListInterface $forms, NodeEnvironmentInterface $env): array
    {
        $methods = [];
        while (($next = $forms->cdr()) instanceof PersistentListInterface
            && $next->first() instanceof PersistentListInterface
        ) {
            $forms = $next;
            /** @var PersistentListInterface<mixed> $methodForm */
            $methodForm = $forms->first();
            $this->assertCompatibleInvoke($methodForm);
            $methods[] = $this->methodBodyAnalyzer->analyze($methodForm, $env);
        }

        return [new DefStructInterface('', $methods), $forms];
    }

    /**
     * A struct is a persistent map, which already defines a callable
     * `__invoke(mixed $key)` for key lookup. A user-supplied `__invoke` must
     * keep a PHP-compatible signature, i.e. accept exactly one required call
     * argument or be variadic; otherwise PHP raises an uncatchable fatal at
     * class-declaration time. Surface that as a clear Phel error instead.
     *
     * @param PersistentListInterface<mixed> $methodForm
     */
    private function assertCompatibleInvoke(PersistentListInterface $methodForm): void
    {
        $name = $methodForm->get(0);
        if (!$name instanceof Symbol || $this->munge->encode($name->getName()) !== '__invoke') {
            return;
        }

        $arguments = $methodForm->get(1);
        if (!$arguments instanceof PersistentVectorInterface) {
            return;
        }

        $variadic = false;
        $required = 0;
        // Skip the leading `this`; count required call args until the `&` tail.
        foreach ($arguments as $index => $argument) {
            if ($index === 0) {
                continue;
            }

            if ($argument instanceof Symbol && $argument->getName() === '&') {
                $variadic = true;
                break;
            }

            ++$required;
        }

        $compatible = $required === 1 || ($required === 0 && $variadic);
        if (!$compatible) {
            throw AnalyzerException::withLocation(
                "A struct's '__invoke' must take exactly one call argument or be variadic "
                . '(e.g. [this x] or [this & xs]), because a struct is already callable as a map. Got '
                . $required . ' required argument(s).',
                $methodForm,
            );
        }
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
