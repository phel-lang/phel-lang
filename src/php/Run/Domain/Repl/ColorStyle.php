<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

/**
 * @psalm-immutable
 */
final class ColorStyle implements ColorStyleInterface
{
    private const DEFAULT_STYLES = [
        'green' => "\033[0;32m%s\033[0m",
        'red' => "\033[31;31m%s\033[0m",
        'yellow' => "\033[33;33m%s\033[0m",
        'blue' => "\033[33;34m%s\033[0m",
    ];

    private array $styles;

    public static function withStyles(array $styles = []): self
    {
        return new self(array_merge(self::DEFAULT_STYLES, $styles));
    }

    public static function noStyles(): self
    {
        return new self([]);
    }

    private function __construct(array $styles)
    {
        $this->styles = $styles;
    }

    public function green(string $str): string
    {
        return $this->color($str, 'green');
    }

    public function yellow(string $str): string
    {
        return $this->color($str, 'yellow');
    }

    public function blue(string $str): string
    {
        return $this->color($str, 'blue');
    }

    public function red(string $str): string
    {
        return $this->color($str, 'red');
    }

    public function color(string $str, ?string $color = null): string
    {
        return sprintf($this->styles[$color] ?? '%s', $str);
    }
}
