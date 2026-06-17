<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use BackedEnum;
use Phel\Api\Transfer\Completion;
use Phel\Api\Transfer\PhpInteropClass;
use Phel\Api\Transfer\PhpInteropSignature;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use Throwable;

use function array_key_exists;
use function array_keys;
use function class_exists;
use function function_exists;
use function get_declared_classes;
use function get_defined_functions;
use function getcwd;
use function implode;
use function interface_exists;
use function is_array;
use function is_bool;
use function is_file;
use function is_float;
use function is_int;
use function is_string;
use function ltrim;
use function preg_replace;
use function preg_split;
use function rtrim;
use function sprintf;
use function str_starts_with;
use function strtolower;
use function trim;

/**
 * Reflection-backed catalog for PHP interop completion, hover, and signature
 * help. Every lookup degrades gracefully: an unknown class, an unloadable
 * symbol, or any reflection failure yields an empty result rather than an
 * error, so the editor never sees a crash or a bogus diagnostic.
 */
final class PhpInteropReflector
{
    /** @var list<class-string>|null */
    private ?array $classmapCache = null;

    public function __construct(
        private readonly ?string $projectRoot = null,
    ) {}

    /**
     * Public instance methods and properties of a class, prefix-filtered.
     * Used after `(php/-> receiver ...)`.
     *
     * @return list<Completion>
     */
    public function instanceMembers(string $class, string $prefix = ''): array
    {
        $reflection = $this->reflect($class);
        if (!$reflection instanceof ReflectionClass) {
            return [];
        }

        $completions = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic()) {
                continue;
            }

            if (str_starts_with($method->getName(), '__')) {
                continue;
            }

            if ($this->matches($method->getName(), $prefix)) {
                $completions[] = $this->methodCompletion($method);
            }
        }

        foreach ($reflection->getProperties() as $property) {
            if (!$property->isPublic()) {
                continue;
            }

            if ($property->isStatic()) {
                continue;
            }

            if ($this->matches($property->getName(), $prefix)) {
                $completions[] = new Completion(
                    label: $property->getName(),
                    kind: Completion::KIND_LOCAL,
                    detail: $this->renderType($property->getType()) . ' property',
                );
            }
        }

        return $completions;
    }

    /**
     * Public static methods and constants of a class, prefix-filtered.
     * Used after `(php/:: Class ...)`.
     *
     * @return list<Completion>
     */
    public function staticMembers(string $class, string $prefix = ''): array
    {
        $reflection = $this->reflect($class);
        if (!$reflection instanceof ReflectionClass) {
            return [];
        }

        $completions = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (!$method->isStatic()) {
                continue;
            }

            if (str_starts_with($method->getName(), '__')) {
                continue;
            }

            if ($this->matches($method->getName(), $prefix)) {
                $completions[] = $this->methodCompletion($method);
            }
        }

        foreach (array_keys($reflection->getConstants()) as $name) {
            if ($this->matches($name, $prefix)) {
                $completions[] = new Completion(
                    label: $name,
                    kind: Completion::KIND_KEYWORD,
                    detail: 'constant',
                );
            }
        }

        return $completions;
    }

    /**
     * PHP class names matching a prefix, drawn from the already-declared
     * classes plus the project's composer classmap when present.
     *
     * @return list<Completion>
     */
    public function classNames(string $prefix = ''): array
    {
        $normalized = ltrim($prefix, '\\');
        $seen = [];
        $completions = [];

        foreach ([...get_declared_classes(), ...$this->classmap()] as $class) {
            if ($this->matches($class, $normalized) && !array_key_exists($class, $seen)) {
                $seen[$class] = true;
                $completions[] = new Completion(
                    label: $class,
                    kind: Completion::KIND_GLOBAL,
                    detail: 'class',
                );
            }
        }

        return $completions;
    }

    /**
     * PHP global (internal + user) functions matching a prefix. Used after
     * the `php/` prefix when it is not one of the interop special forms.
     *
     * @return list<Completion>
     */
    public function globalFunctions(string $prefix = ''): array
    {
        $defined = get_defined_functions();
        $completions = [];

        foreach ([...$defined['internal'], ...$defined['user']] as $function) {
            if ($this->matches($function, $prefix)) {
                $completions[] = new Completion(
                    label: $function,
                    kind: Completion::KIND_GLOBAL,
                    detail: $this->functionSignature($function) ?? 'function',
                );
            }
        }

        return $completions;
    }

    /**
     * `name(params): return` for a class method, or null when unresolvable.
     */
    public function methodSignature(string $class, string $method): ?string
    {
        return $this->methodSignatureInfo($class, $method)?->label;
    }

    /**
     * Structured signature (label, per-parameter substrings, phpdoc) for a class
     * method, or null when unresolvable. Used for signature help.
     */
    public function methodSignatureInfo(string $class, string $method): ?PhpInteropSignature
    {
        $reflection = $this->reflect($class);
        if (!$reflection instanceof ReflectionClass) {
            return null;
        }

        try {
            return $this->methodInfo($reflection->getMethod($method));
        } catch (ReflectionException) {
            return null;
        }
    }

    /**
     * Whether the given class or interface can be reflected.
     */
    public function classExists(string $class): bool
    {
        return $this->reflect($class) instanceof ReflectionClass;
    }

    /**
     * `name(params): return` for a global function, or null when unresolvable.
     */
    public function functionSignature(string $function): ?string
    {
        return $this->functionSignatureInfo($function)?->label;
    }

    /**
     * Structured signature (label, per-parameter substrings, phpdoc) for a
     * global function, or null when unresolvable.
     */
    public function functionSignatureInfo(string $function): ?PhpInteropSignature
    {
        if (!function_exists($function)) {
            return null;
        }

        try {
            $reflection = new ReflectionFunction($function);
        } catch (ReflectionException) {
            return null;
        }

        return new PhpInteropSignature(
            sprintf(
                '%s(%s)%s',
                $function,
                $this->renderParameters($reflection->getParameters()),
                $this->renderReturn($reflection->getReturnType()),
            ),
            $this->parameterLabels($reflection->getParameters()),
            $this->cleanDoc($reflection->getDocComment()),
        );
    }

    /**
     * Hover signature for an instance member of a class: a non-static method or
     * a public instance property. Null when neither resolves.
     */
    public function instanceMemberInfo(string $class, string $member): ?PhpInteropSignature
    {
        $method = $this->methodSignatureInfo($class, $member);
        if ($method instanceof PhpInteropSignature) {
            return $method;
        }

        $reflection = $this->reflect($class);
        if (!$reflection instanceof ReflectionClass || !$reflection->hasProperty($member)) {
            return null;
        }

        $property = $reflection->getProperty($member);
        if (!$property->isPublic() || $property->isStatic()) {
            return null;
        }

        $type = $this->renderType($property->getType());
        $label = ($type === '' ? '' : $type . ' ') . '$' . $property->getName();

        return new PhpInteropSignature($label, [], $this->cleanDoc($property->getDocComment()));
    }

    /**
     * Hover signature for a static member of a class: a static method, a class
     * constant, or an enum case. Null when none resolves.
     */
    public function staticMemberInfo(string $class, string $member): ?PhpInteropSignature
    {
        $method = $this->methodSignatureInfo($class, $member);
        if ($method instanceof PhpInteropSignature) {
            return $method;
        }

        $reflection = $this->reflect($class);
        if (!$reflection instanceof ReflectionClass || !$reflection->hasConstant($member)) {
            return null;
        }

        try {
            $constant = $reflection->getReflectionConstant($member);
        } catch (ReflectionException) {
            return null;
        }

        if ($constant === false || !$constant->isPublic()) {
            return null;
        }

        return new PhpInteropSignature(
            $this->renderConstant($constant),
            [],
            $this->cleanDoc($constant->getDocComment()),
        );
    }

    /**
     * Reflected class details for hover (kind, parent, interfaces, phpdoc,
     * constructor), or null when the class cannot be reflected.
     */
    public function classInfo(string $class): ?PhpInteropClass
    {
        $reflection = $this->reflect($class);
        if (!$reflection instanceof ReflectionClass) {
            return null;
        }

        $parent = $reflection->getParentClass();

        return new PhpInteropClass(
            $reflection->getName(),
            $this->classKind($reflection),
            $parent === false ? null : $parent->getName(),
            $reflection->getInterfaceNames(),
            $this->cleanDoc($reflection->getDocComment()),
            $this->methodSignatureInfo($class, '__construct'),
        );
    }

    private function methodCompletion(ReflectionMethod $method): Completion
    {
        return new Completion(
            label: $method->getName(),
            kind: Completion::KIND_MACRO,
            detail: $this->renderMethod($method),
        );
    }

    private function methodInfo(ReflectionMethod $method): PhpInteropSignature
    {
        return new PhpInteropSignature(
            $this->renderMethod($method),
            $this->parameterLabels($method->getParameters()),
            $this->cleanDoc($method->getDocComment()),
        );
    }

    private function renderMethod(ReflectionMethod $method): string
    {
        return sprintf(
            '%s(%s)%s',
            $method->getName(),
            $this->renderParameters($method->getParameters()),
            $this->renderReturn($method->getReturnType()),
        );
    }

    /**
     * @param list<ReflectionParameter> $parameters
     */
    private function renderParameters(array $parameters): string
    {
        return implode(', ', $this->parameterLabels($parameters));
    }

    /**
     * One `type $name` label per parameter, each a substring of the rendered
     * method/function label so an editor can highlight the active argument.
     *
     * @param list<ReflectionParameter> $parameters
     *
     * @return list<string>
     */
    private function parameterLabels(array $parameters): array
    {
        $labels = [];
        foreach ($parameters as $parameter) {
            $type = $this->renderType($parameter->getType());
            $name = ($parameter->isVariadic() ? '...$' : '$') . $parameter->getName();
            $labels[] = $type === '' ? $name : $type . ' ' . $name;
        }

        return $labels;
    }

    /**
     * A raw `getDocComment()` result as plain text (delimiters and leading `*`
     * stripped), or an empty string when there is none. Internal PHP symbols
     * rarely carry doc comments, so this is usually empty for them.
     */
    private function cleanDoc(string|false $doc): string
    {
        if ($doc === false) {
            return '';
        }

        $lines = preg_split('/\r?\n/', $doc) ?: [];
        $cleaned = [];
        foreach ($lines as $line) {
            $line = trim($line);
            $line = preg_replace('#^/\*\*+#', '', $line) ?? $line;
            $line = preg_replace('#\*+/$#', '', $line) ?? $line;
            $line = preg_replace('#^\*\s?#', '', $line) ?? $line;
            $cleaned[] = rtrim($line);
        }

        return trim(implode("\n", $cleaned));
    }

    private function renderReturn(?ReflectionType $type): string
    {
        $rendered = $this->renderType($type);

        return $rendered === '' ? '' : ': ' . $rendered;
    }

    private function renderType(?ReflectionType $type): string
    {
        if ($type instanceof ReflectionNamedType) {
            return ($type->allowsNull() && $type->getName() !== 'null' && $type->getName() !== 'mixed' ? '?' : '')
                . $type->getName();
        }

        if ($type instanceof ReflectionType) {
            return (string) $type;
        }

        return '';
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function classKind(ReflectionClass $reflection): string
    {
        return match (true) {
            $reflection->isInterface() => 'interface',
            $reflection->isEnum() => 'enum',
            $reflection->isTrait() => 'trait',
            $reflection->isAbstract() => 'abstract class',
            $reflection->isFinal() => 'final class',
            default => 'class',
        };
    }

    private function renderConstant(ReflectionClassConstant $constant): string
    {
        if ($constant->isEnumCase()) {
            $value = $constant->getValue();
            $suffix = $value instanceof BackedEnum ? ' = ' . $this->renderConstantValue($value->value) : '';

            return 'case ' . $constant->getName() . $suffix;
        }

        $rendered = $this->renderConstantValue($constant->getValue());

        return 'const ' . $constant->getName() . ($rendered === '' ? '' : ' = ' . $rendered);
    }

    private function renderConstantValue(mixed $value): string
    {
        return match (true) {
            is_string($value) => '"' . $value . '"',
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            is_int($value), is_float($value) => (string) $value,
            default => '',
        };
    }

    /**
     * @return ReflectionClass<object>|null
     */
    private function reflect(string $class): ?ReflectionClass
    {
        $name = ltrim($class, '\\');
        if ($name === '' || (!class_exists($name) && !interface_exists($name))) {
            return null;
        }

        return new ReflectionClass($name);
    }

    private function matches(string $candidate, string $prefix): bool
    {
        if ($prefix === '') {
            return true;
        }

        return str_starts_with(strtolower($candidate), strtolower($prefix));
    }

    /**
     * @return list<class-string>
     */
    private function classmap(): array
    {
        if ($this->classmapCache !== null) {
            return $this->classmapCache;
        }

        $this->classmapCache = [];

        $root = $this->projectRoot ?? (getcwd() ?: null);
        if ($root === null) {
            return $this->classmapCache;
        }

        $classmapFile = $root . '/vendor/composer/autoload_classmap.php';
        if (!is_file($classmapFile)) {
            return $this->classmapCache;
        }

        try {
            /** @var mixed $map */
            $map = require $classmapFile;
        } catch (Throwable) {
            return $this->classmapCache;
        }

        if (is_array($map)) {
            /** @var list<class-string> $keys */
            $keys = array_keys($map);
            $this->classmapCache = $keys;
        }

        return $this->classmapCache;
    }
}
