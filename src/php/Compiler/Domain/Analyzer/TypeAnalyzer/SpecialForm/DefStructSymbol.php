<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\DefStructInterface;
use Phel\Compiler\Domain\Analyzer\Ast\DefStructNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Struct\AbstractPersistentStruct;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Munge;
use ReflectionClass;

use function count;
use function interface_exists;
use function is_a;
use function method_exists;
use function sprintf;
use function str_starts_with;

/**
 * (defstruct Name [fields...]).
 *
 * Defines a struct type with named fields and a positional constructor.
 *
 * @internal
 */
final readonly class DefStructSymbol implements SpecialFormAnalyzerInterface
{
    public function __construct(
        private AnalyzerInterface $analyzer,
        private InterfaceImplementationsAnalyzer $implementationsAnalyzer,
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
                errorCode: ErrorCode::ARITY_ERROR,
            );
        }

        $structSymbol = $list->get(1);
        if (!($structSymbol instanceof Symbol)) {
            throw AnalyzerException::wrongArgumentType("First argument of 'defstruct", 'Symbol', $structSymbol, $list);
        }

        ReservedDeclarationName::assertType($structSymbol);

        $structParams = $list->get(2);
        if (!($structParams instanceof PersistentVectorInterface)) {
            throw AnalyzerException::wrongArgumentType("Second argument of 'defstruct", 'Vector', $structParams, $list);
        }

        /** @var PersistentListInterface<mixed> $rest1 */
        $rest1 = $list->rest();
        /** @var PersistentListInterface<mixed> $rest2 */
        $rest2 = $rest1->rest();
        /** @var PersistentListInterface<mixed> $rest3 */
        $rest3 = $rest2->rest();

        [$params, $interfaces] = TypeTagGuard::whileDeclaring($structSymbol, function () use ($structParams, $rest3, $env): array {
            $params = $this->params($structParams);

            return [$params, $this->implementationsAnalyzer->analyze(
                $rest3,
                $env->withMergedLocals($params),
                'defstruct',
            )];
        });
        $this->analyzer->addPhpClass($this->analyzer->getNamespace(), $structSymbol);
        $this->rejectMethodsTheBaseDeclares($interfaces);

        return new DefStructNode(
            $env,
            $this->analyzer->getNamespace(),
            $structSymbol,
            $params,
            $interfaces,
            $list->getStartLocation(),
        );
    }

    /**
     * A struct method overrides the base method of the same name. PHP rejects
     * the override when it drops the base's return type, and otherwise it
     * silently replaces what lookups, equality and printing call. A method
     * of an interface the base already implements, or a magic method in a
     * `:php` block (no interface name), is a deliberate override.
     *
     * @param list<DefStructInterface> $interfaces
     */
    private function rejectMethodsTheBaseDeclares(array $interfaces): void
    {
        $base = new ReflectionClass(AbstractPersistentStruct::class);
        $munge = new Munge();
        foreach ($interfaces as $interface) {
            $interfaceName = $interface->getAbsoluteInterfaceName();
            foreach ($interface->getMethods() as $method) {
                $name = $method->getName();
                $phpName = $munge->encode($name->getName());
                if (($interfaceName === '' && str_starts_with($phpName, '__'))
                    || !$base->hasMethod($phpName)
                    || $base->getMethod($phpName)->isPrivate()
                    || $this->baseImplementsInterfaceDeclaring($interfaceName, $phpName)
                ) {
                    continue;
                }

                throw AnalyzerException::withLocation(
                    sprintf('Method %s is reserved: every struct already has a %s() method', $name->getName(), $base->getMethod($phpName)->getName()),
                    $name,
                    errorCode: ErrorCode::INVALID_SPECIAL_FORM,
                );
            }
        }
    }

    /**
     * Whether the interface, or one it extends, declares the method and is
     * implemented by the base, so the override keeps the base's signature.
     */
    private function baseImplementsInterfaceDeclaring(string $interfaceName, string $phpName): bool
    {
        if (!interface_exists($interfaceName)) {
            return false;
        }

        return array_any(
            [$interfaceName, ...new ReflectionClass($interfaceName)->getInterfaceNames()],
            static fn(string $declaring): bool => method_exists($declaring, $phpName)
                && is_a(AbstractPersistentStruct::class, $declaring, true),
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
        $phpNames = [];
        $munge = new Munge();
        $inherited = $this->inheritedPropertyNames();
        foreach ($vector as $element) {
            if (!($element instanceof Symbol)) {
                throw AnalyzerException::withLocation('Defstruct field elements must be Symbols.', $vector, errorCode: ErrorCode::TYPE_ERROR);
            }

            $phpName = $munge->encode($element->getName());
            if (isset($phpNames[$phpName])) {
                throw AnalyzerException::withLocation(
                    $phpNames[$phpName] === $element->getName()
                        ? sprintf('Field %s is declared more than once', $element->getName())
                        : sprintf('Fields %s and %s are the same PHP property $%s', $phpNames[$phpName], $element->getName(), $phpName),
                    $element,
                    errorCode: ErrorCode::INVALID_SPECIAL_FORM,
                );
            }

            if (isset($inherited[$phpName])) {
                throw AnalyzerException::withLocation(
                    sprintf('Field %s is reserved: every struct already has a $%s property', $element->getName(), $phpName),
                    $element,
                    errorCode: ErrorCode::INVALID_SPECIAL_FORM,
                );
            }

            $phpNames[$phpName] = $element->getName();
            $param = TagCanonicalizer::symbol($element, $this->analyzer);
            TypeTagGuard::assertSymbol($param, $this->analyzer);
            $params[] = $param;
        }

        return $params;
    }

    /**
     * Read off the base class, so a member added there is reported here
     * instead of reaching PHP as a fatal error.
     *
     * @return array<string, true>
     */
    private function inheritedPropertyNames(): array
    {
        $names = [];
        foreach (new ReflectionClass(AbstractPersistentStruct::class)->getProperties() as $property) {
            if (!$property->isPrivate()) {
                $names[$property->getName()] = true;
            }
        }

        return $names;
    }
}
