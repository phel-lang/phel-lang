<?php

declare(strict_types=1);

namespace Phel\Lang;

use ArrayAccess;
use Override;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;

/**
 * @extends AbstractType<string>
 */
final class Symbol extends AbstractType implements IdenticalInterface, FnInterface, NamedInterface
{
    use MetaTrait;

    public const string NAME_APPLY = 'apply';

    public const string NAME_CONCAT = 'concat';

    public const string NAME_DEF = 'def';

    public const string NAME_DEF_ONCE = 'defonce';

    public const string NAME_DEF_STRUCT = 'defstruct*';

    public const string NAME_DO = 'do';

    public const string NAME_FN = 'fn';

    public const string NAME_FOREACH = 'foreach';

    public const string NAME_IF = 'if';

    public const string NAME_LET = 'let';

    public const string NAME_LOOP = 'loop';

    public const string NAME_NS = 'ns';

    public const string NAME_CONJ = 'conj';

    public const string NAME_PHP_ARRAY_GET = 'php/aget';

    public const string NAME_PHP_ARRAY_PUSH = 'php/apush';

    public const string NAME_PHP_ARRAY_SET = 'php/aset';

    public const string NAME_PHP_ARRAY_UNSET = 'php/aunset';

    public const string NAME_PHP_ARRAY_GET_IN = 'php/aget-in';

    public const string NAME_PHP_ARRAY_PUSH_IN = 'php/apush-in';

    public const string NAME_PHP_ARRAY_SET_IN = 'php/aset-in';

    public const string NAME_PHP_ARRAY_UNSET_IN = 'php/aunset-in';

    public const string NAME_PHP_NEW = 'php/new';

    public const string NAME_NEW = 'new';

    public const string NAME_PHP_OBJECT_CALL = 'php/->';

    public const string NAME_PHP_REF = 'php/ref';

    public const string NAME_PHP_OBJECT_STATIC_CALL = 'php/::';

    public const string NAME_PHP_CALLABLE = 'php/callable';

    public const string NAME_QUOTE = 'quote';

    public const string NAME_VAR = 'var';

    public const string NAME_RECUR = 'recur';

    public const string NAME_UNQUOTE = 'unquote';

    public const string NAME_UNQUOTE_SPLICING = 'unquote-splicing';

    public const string NAME_DEREF = 'deref';

    public const string NAME_THROW = 'throw';

    public const string NAME_TRY = 'try';

    public const string NAME_CATCH = 'catch';

    public const string NAME_FINALLY = 'finally';

    public const string NAME_PHP_OBJECT_SET = 'php/oset';

    public const string NAME_LIST = 'list';

    public const string NAME_VECTOR = 'vector';

    public const string NAME_MAP = 'hash-map';

    public const string NAME_SET_VAR = 'set-var';

    public const string NAME_DEF_INTERFACE = 'definterface*';

    public const string NAME_DEF_EXCEPTION = 'defexception*';

    public const string NAME_DEF_ENUM = 'defenum*';

    public const string NAME_REIFY = 'reify*';

    public const string NAME_DOLLAR = '$';

    public const string NAME_HASH = '#';

    public const string NAME_LOAD = 'load';

    public const string NAME_IN_NS = 'in-ns';

    public const string NAME_USE = 'use';

    private static int $symGenCounter = 1;

    private readonly int $hash;

    public function __construct(
        private readonly ?string $namespace,
        private readonly string $name,
    ) {
        $this->hash = crc32($name);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->getFullName();
    }

    /**
     * Symbol-as-accessor, mirroring `Keyword::__invoke`. `nil` target
     * returns the default so `('foo nil)` is `nil` rather than raising.
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

    public static function create(string $name): self
    {
        $pos = strpos($name, '/');

        if ($pos === false || $name === '/') {
            return new self(null, $name);
        }

        return new self(substr($name, 0, $pos), substr($name, $pos + 1));
    }

    public static function createForNamespace(?string $namespace, string $name): self
    {
        return new self($namespace, $name);
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
        if ($this->namespace !== null && $this->namespace !== '') {
            return $this->displayNamespace() . '/' . $this->name;
        }

        return $this->name;
    }

    public static function gen(string $prefix = '__phel_'): self
    {
        return self::create($prefix . (self::$symGenCounter++));
    }

    public static function resetGen(): void
    {
        self::$symGenCounter = 1;
    }

    /**
     * Current value of the global gensym counter.
     *
     * Exposed so the reader-result cache can record how many gensyms a form's
     * read phase consumed and replay that exact advance on a warm rebuild,
     * keeping later analyzer/emitter gensym names byte-identical to a cold
     * compile.
     */
    public static function genCounter(): int
    {
        return self::$symGenCounter;
    }

    /**
     * Advance the global gensym counter without producing symbols, standing in
     * for a read phase that was served from the reader-result cache.
     */
    public static function advanceGenCounter(int $by): void
    {
        if ($by > 0) {
            self::$symGenCounter += $by;
        }
    }

    public function hash(): int
    {
        return $this->hash;
    }

    public function equals(mixed $other): bool
    {
        // Identity is sufficient (but not necessary): Symbols are not interned
        // (each carries a per-occurrence source location), so two value-equal
        // Symbols may be distinct instances. The `===` true case is a correct
        // shortcut; the false case falls through to direct field comparison.
        return $this === $other
            || ($other instanceof self
                && $this->name === $other->name
                && $this->namespace === $other->namespace);
    }

    public function identical(mixed $other): bool
    {
        return $this->equals($other);
    }

    private function displayNamespace(): string
    {
        $namespace = $this->namespace ?? '';

        // PHP class FQNs (leading `\\`) keep their backslash form so static
        // method calls compile correctly. Plain Phel namespaces translate to
        // dot to match the canonical form.
        if (isset($namespace[0]) && $namespace[0] === '\\') {
            return $namespace;
        }

        return str_replace('\\', '.', $namespace);
    }
}
