<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

final readonly class EvalError
{
    /**
     * @param list<StackFrame> $frames
     */
    public function __construct(
        public string $exceptionClass,
        public string $message,
        public ?string $errorCode,
        public ?string $file,
        public ?int $line,
        public ?int $column,
        public ?int $endLine,
        public ?int $endColumn,
        public ?string $codeSnippet,
        public string $stackTrace,
        public string $phase,
        public array $frames = [],
    ) {}
}
