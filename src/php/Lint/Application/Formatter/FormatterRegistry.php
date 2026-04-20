<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Formatter;

use InvalidArgumentException;
use Phel\Lint\Domain\DiagnosticFormatterInterface;

use function sprintf;

/**
 * Small, open-for-extension lookup: add a new formatter by instantiating
 * it and calling `register()`. Callers ask by name (`human`, `json`,
 * `github`, ...).
 */
final class FormatterRegistry
{
    /** @var array<string, DiagnosticFormatterInterface> */
    private array $formatters = [];

    public function register(DiagnosticFormatterInterface $formatter): void
    {
        $this->formatters[$formatter->name()] = $formatter;
    }

    public function get(string $name): DiagnosticFormatterInterface
    {
        if (!isset($this->formatters[$name])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown lint formatter: %s. Known: %s.',
                $name,
                implode(', ', array_keys($this->formatters)),
            ));
        }

        return $this->formatters[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->formatters[$name]);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->formatters);
    }
}
