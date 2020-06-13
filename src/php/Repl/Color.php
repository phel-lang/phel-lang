<?php

declare(strict_types=1);

namespace Phel\Repl;

/** @psalm-immutable */
final class Color
{
    private const DEFAULT_STYLES = [
        'green' => "\033[0;32m%s\033[0m",
        'red' => "\033[31;31m%s\033[0m",
        'yellow' => "\033[33;33m%s\033[0m",
        'blue' => "\033[33;34m%s\033[0m",
    ];

    private array $styles;

    public static function withDefaultStyles(): self
    {
        return new self(self::DEFAULT_STYLES);
    }

    private function __construct(array $styles)
    {
        $this->styles = $styles;
    }

    public function prompt(string $text, ?string $color = null): string
    {
        return sprintf($this->styles[$color] ?? "%s", $text);
    }
}
