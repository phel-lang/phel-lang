<?php

declare(strict_types=1);

namespace Phel\Lang;

use ArrayAccess;
use Override;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;

/**
 * @extends AbstractType<string>
 */
final class Keyword extends AbstractType implements IdenticalInterface, FnInterface, NamedInterface
{
    use MetaTrait;

    /**
     * Two-level pool keyed by namespace then name, so (ns nil, name "a/b")
     * and (ns "a", name "b") never share a slot. The `"\0"` sentinel keeps a
     * nil namespace distinct from a legitimate empty-string namespace.
     *
     * @var array<string, array<string, self>>
     */
    private static array $internPool = [];

    private readonly int $hash;

    private function __construct(
        private readonly ?string $namespace,
        private readonly string $name,
    ) {
        $this->hash = $namespace !== null
            ? crc32(':' . $namespace . '/' . $name)
            : crc32(':' . $name);
    }

    /**
     * Keyword-as-accessor. `nil` target returns the default (matches
     * `(:k nil)` returning nil) instead of raising `TypeError`.
     */
    public function __invoke(
        mixed $obj,
        float|bool|int|string|TypeInterface|null $default = null,
    ): mixed {
        if ($obj instanceof ArrayAccess) {
            if ($obj instanceof ContainsInterface) {
                return $obj->contains($this) ? $obj[$this] : $default;
            }

            return $obj[$this] ?? $default;
        }

        if ($obj instanceof PersistentHashSetInterface) {
            return $obj->contains($this) ? $this : $default;
        }

        return $default;
    }

    #[Override]
    public function __toString(): string
    {
        return ':' . $this->getFullName();
    }

    /**
     * When no namespace is given, the name is split on the first `/` exactly
     * like {@see Symbol::create}, so `(keyword "a/b/c")` yields namespace `a`
     * and name `b/c` (Clojure-aligned). An explicit namespace keeps the name
     * verbatim.
     */
    public static function create(string $name, ?string $namespace = null): self
    {
        if ($namespace === null) {
            $pos = strpos($name, '/');

            if ($pos !== false && $name !== '/') {
                return self::intern(substr($name, 0, $pos), substr($name, $pos + 1));
            }
        }

        return self::intern($namespace, $name);
    }

    /**
     * Verbatim constructor mirroring {@see Symbol::createForNamespace}: the
     * name is never split, so `createForNamespace(null, 'a/b')` is an
     * unqualified keyword named `a/b`.
     */
    public static function createForNamespace(?string $namespace, string $name): self
    {
        return self::intern($namespace, $name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function getFullName(): string
    {
        if ($this->namespace !== null) {
            return $this->namespace . '/' . $this->name;
        }

        return $this->name;
    }

    public function hash(): int
    {
        return $this->hash;
    }

    public function equals(mixed $other): bool
    {
        // Value-based, not identity: keywords are interned so value-equal
        // keywords are normally the same instance, but `unserialize` rebuilds a
        // Keyword outside the intern pool (the read-result cache serializes live
        // keyword-keyed maps). A deserialized `:macro` key must still compare
        // equal to a freshly-created `Keyword::create("macro")`, or map lookups
        // against cached forms silently miss. Mirrors `Symbol::equals`.
        return $this === $other
            || ($other instanceof self
                && $this->name === $other->name
                && $this->namespace === $other->namespace);
    }

    public function identical(mixed $other): bool
    {
        return $this === $other;
    }

    /**
     * Interned keywords are shared instances — source location is
     * per-occurrence, not per-keyword, so mutation must be suppressed.
     */
    #[Override]
    public function setStartLocation(?SourceLocation $startLocation): static
    {
        return $this;
    }

    #[Override]
    public function setEndLocation(?SourceLocation $endLocation): static
    {
        return $this;
    }

    private static function intern(?string $namespace, string $name): self
    {
        return self::$internPool[$namespace ?? "\0"][$name] ??= new self($namespace, $name);
    }
}
