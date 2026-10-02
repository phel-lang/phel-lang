<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Exceptions;

use Phel\Lang\Registry;
use Phel\Shared\MungeInterface;
use ReflectionClass;

use function is_string;
use function str_replace;
use function strrpos;
use function substr;

/**
 * Names a compiled Phel fn class the way Phel code spells the var it was
 * defined under, `app.main/add-it`, from the class's `BOUND_TO` constant. The
 * constant holds the PHP spelling, `app\main\add_it`, which is not something a
 * user can paste back into code (#3460).
 *
 * @internal
 */
final readonly class CompiledFnName
{
    public function __construct(
        private MungeInterface $munge,
    ) {}

    /**
     * `namespace/name`, or null when the class carries no `BOUND_TO`. The name
     * comes from the registry when it still holds the fn, and is decoded from
     * `BOUND_TO` otherwise, which reads every `_` as `-`.
     *
     * @param class-string $class
     */
    public function displayName(string $class): ?string
    {
        $namespace = $this->namespaceOf($class);
        if ($namespace === null) {
            return null;
        }

        return $namespace . '/' . ($this->definedName($class) ?? $this->munge->decodeNs($this->compiledName($class)));
    }

    /**
     * Every `_` reads as `-`. Phel resolves `my_app.core` and `my-app.core` to
     * one namespace, so the hyphen spelling names the same var either way.
     *
     * @param class-string $class
     */
    public function namespaceOf(string $class): ?string
    {
        $boundTo = $this->boundTo($class);
        if ($boundTo === null) {
            return null;
        }

        $lastSeparator = strrpos($boundTo, '\\');
        if ($lastSeparator === false) {
            return null;
        }

        return str_replace('\\', '.', $this->munge->decodeNs(substr($boundTo, 0, $lastSeparator)));
    }

    /**
     * The last segment of `BOUND_TO`, as the compiler wrote it.
     *
     * @param class-string $class
     */
    public function compiledName(string $class): string
    {
        $boundTo = $this->boundTo($class) ?? '';
        $lastSeparator = strrpos($boundTo, '\\');

        return $lastSeparator === false ? $boundTo : substr($boundTo, $lastSeparator + 1);
    }

    /**
     * The name the fn was defined under. `BOUND_TO` writes `-` as `_`, so
     * `add-it` and `my_fn` both end in an underscore there, and only the
     * registry still knows which one the source spelled. A var that only
     * aliases the fn, as `(def g f)` does, encodes to another name and is
     * skipped.
     *
     * @param class-string $class
     */
    public function definedName(string $class): ?string
    {
        $boundTo = $this->boundTo($class);
        $lastSeparator = $boundTo === null ? false : strrpos($boundTo, '\\');
        if ($boundTo === null || $lastSeparator === false) {
            return null;
        }

        $registryNs = str_replace('\\', '.', substr($boundTo, 0, $lastSeparator));
        $compiledName = substr($boundTo, $lastSeparator + 1);
        foreach (Registry::getInstance()->getDefinitionInNamespace($registryNs) as $name => $value) {
            if ($value instanceof $class && $this->munge->encodePhpNs((string) $name) === $compiledName) {
                return (string) $name;
            }
        }

        return null;
    }

    /**
     * @param class-string $class
     */
    private function boundTo(string $class): ?string
    {
        $reflection = new ReflectionClass($class);
        $boundTo = $reflection->hasConstant('BOUND_TO') ? $reflection->getConstant('BOUND_TO') : null;

        return is_string($boundTo) && $boundTo !== '' ? $boundTo : null;
    }
}
