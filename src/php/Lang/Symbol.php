<?php

declare(strict_types=1);

namespace Phel\Lang;

final class Symbol extends AbstractType implements IdenticalInterface, NamedInterface
{
    use MetaTrait;

    public const NAME_APPLY = 'apply';

    public const NAME_CONCAT = 'concat';

    public const NAME_DEF = 'def';

    public const NAME_DEF_STRUCT = 'defstruct*';

    public const NAME_DO = 'do';

    public const NAME_FN = 'fn';

    public const NAME_FOREACH = 'foreach';

    public const NAME_IF = 'if';

    public const NAME_LET = 'let';

    public const NAME_LOOP = 'loop';

    public const NAME_NS = 'ns';

    public const NAME_PHP_ARRAY_GET = 'php/aget';

    public const NAME_PHP_ARRAY_PUSH = 'php/apush';

    public const NAME_PHP_ARRAY_SET = 'php/aset';

    public const NAME_PHP_ARRAY_UNSET = 'php/aunset';

    public const NAME_PHP_NEW = 'php/new';

    public const NAME_PHP_OBJECT_CALL = 'php/->';

    public const NAME_PHP_OBJECT_STATIC_CALL = 'php/::';

    public const NAME_QUOTE = 'quote';

    public const NAME_RECUR = 'recur';

    public const NAME_UNQUOTE = 'unquote';

    public const NAME_UNQUOTE_SPLICING = 'unquote-splicing';

    public const NAME_THROW = 'throw';

    public const NAME_TRY = 'try';

    public const NAME_PHP_OBJECT_SET = 'php/oset';

    public const NAME_LIST = 'list';

    public const NAME_VECTOR = 'vector';

    public const NAME_MAP = 'hash-map';

    public const NAME_SET_VAR = 'set-var';

    public const NAME_DEF_INTERFACE = 'definterface*';

    private static int $symGenCounter = 1;

    public function __construct(
        private readonly ?string $namespace,
        private readonly string $name,
    ) {
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
            return $this->namespace . '/' . $this->name;
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

    public function hash(): int
    {
        return crc32($this->name);
    }

    public function equals(mixed $other): bool
    {
        return $other instanceof self
            && $this->name === $other->getName()
            && $this->namespace === $other->getNamespace();
    }

    public function identical(mixed $other): bool
    {
        return $this->equals($other);
    }
}
