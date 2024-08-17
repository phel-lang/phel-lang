<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

use function is_bool;
use function is_null;
use function is_string;
use function sprintf;

final readonly class InputResult
{
    private const NO_VALUE = 'no_value';

    private const LAST_RESULT_PLACEHOLDER = '$_';

    private function __construct(private mixed $lastResult)
    {
    }

    public static function fromAny(mixed $result): self
    {
        return new self($result);
    }

    public static function empty(): self
    {
        return new self(self::NO_VALUE);
    }

    public function readBuffer(array $buffer): string
    {
        $fullInput = implode(PHP_EOL, $buffer);

        if ($this->lastResult === self::NO_VALUE
            || !str_contains($fullInput, self::LAST_RESULT_PLACEHOLDER)
        ) {
            return $fullInput;
        }

        return preg_replace(
            '/"[^\\"]*(?:\\.|[^\\"]*)*"(*SKIP)(*F)|' . preg_quote(self::LAST_RESULT_PLACEHOLDER, '/') . '/',
            $this->formattedLastResult(),
            $fullInput,
        );
    }

    private function formattedLastResult(): string
    {
        if (is_string($this->lastResult)) {
            return sprintf('"%s"', $this->lastResult);
        }

        if (is_bool($this->lastResult)) {
            return $this->lastResult ? 'true' : 'false';
        }

        if (is_null($this->lastResult)) {
            return 'nil';
        }

        return (string)$this->lastResult;
    }
}
