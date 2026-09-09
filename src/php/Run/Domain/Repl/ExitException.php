<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

use RuntimeException;

/**
 * @internal
 */
final class ExitException extends RuntimeException
{
    private function __construct(
        string $message,
        private readonly int $status,
    ) {
        parent::__construct($message);
    }

    public static function fromRepl(int $status = 0): self
    {
        return new self('Exit from REPL!', $status);
    }

    public function status(): int
    {
        return $this->status;
    }
}
