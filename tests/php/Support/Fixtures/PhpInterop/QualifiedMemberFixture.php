<?php

declare(strict_types=1);

namespace PhelTest\Support\Fixtures\PhpInterop;

/**
 * Fixture for qualified members in value position: it carries a class constant
 * and a static method under the same name (`new`), which PHP allows and which
 * is why `\C/new` cannot mean "constructor" in Phel.
 */
final readonly class QualifiedMemberFixture
{
    public const string new = 'i-am-a-constant';

    public const string LABEL = 'fixture';

    public function __construct(
        public int $id = 0,
    ) {}

    public static function new(int $id): self
    {
        return new self($id);
    }

    public static function upper(string $value): string
    {
        return strtoupper($value);
    }

    public function label(): string
    {
        return self::LABEL . '-' . $this->id;
    }

    public function repeatLabel(int $times): string
    {
        return str_repeat($this->label(), $times);
    }
}
