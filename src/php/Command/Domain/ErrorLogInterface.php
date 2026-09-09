<?php

declare(strict_types=1);

namespace Phel\Command\Domain;

/**
 * @internal
 */
interface ErrorLogInterface
{
    /**
     * Appends one entry, headed by a timestamp and the command line, with
     * terminal escapes removed.
     */
    public function writeln(string $text): void;
}
