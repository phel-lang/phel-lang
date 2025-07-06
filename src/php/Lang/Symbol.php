<?php

declare(strict_types=1);

namespace Phel\Lang;

final class Symbol extends AbstractType implements IdenticalInterface, NamedInterface
{
    use MetaTrait;

    public const string NAME_APPLY = 'apply';

    public const string NAME_CONCAT = 'concat';

    public const string NAME_DEF = 'def';

    public const string NAME_DEF_STRUCT = 'defstruct*';

    public const string NAME_DO = 'do';

    public const string NAME_FN = 'fn';

    public const string NAME_FOREACH = 'foreach';

    public const string NAME_IF = 'if';

    public const string NAME_LET = 'let';

    public const string NAME_LOOP = 'loop';

    public const string NAME_NS = 'ns';

    public const string NAME_PHP_ARRAY_GET = 'php/aget';

    public const string NAME_PHP_ARRAY_PUSH = 'php/apush';

    public const string NAME_PHP_ARRAY_SET = 'php/aset';

    public const string NAME_PHP_ARRAY_UNSET = 'php/aunset';

    public const string NAME_PHP_NEW = 'php/new';

    public const string NAME_PHP_OBJECT_CALL = 'php/->';

    public const string NAME_PHP_OBJECT_STATIC_CALL = 'php/::';

    public const string NAME_QUOTE = 'quote';

    public const string NAME_RECUR = 'recur';

    public const string NAME_UNQUOTE = 'unquote';

    public const string NAME_UNQUOTE_SPLICING = 'unquote-splicing';

    public const string NAME_THROW = 'throw';

    public const string NAME_TRY = 'try';

    public const string NAME_PHP_OBJECT_SET = 'php/oset';

    public const string NAME_LIST = 'list';

    public const string NAME_VECTOR = 'vector';

    public const string NAME_MAP = 'hash-map';

    public const string NAME_SET_VAR = 'set-var';

    public const string NAME_DEF_INTERFACE = 'definterface*';

    public const string NAME_DEF_EXCEPTION = 'defexception*';

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
