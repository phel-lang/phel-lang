<?php

declare(strict_types=1);

namespace Phel\Shared\Exceptions;

/**
 * What one {@see ErrorCode} means, in the four parts a user needs to act on it:
 * what the code is called, what it means, the smallest program that raises it,
 * and what to change.
 *
 * The same value renders the terminal output of `phel explain` and the pages
 * under `docs/errors/`, so the two can never drift.
 */
final readonly class ErrorCodeExplanation
{
    /**
     * @param string $title   a short noun phrase, the heading of the docs entry
     * @param string $summary what the error means, in one or two sentences
     * @param string $example the smallest Phel snippet that raises it
     * @param string $fix     what to change to make it go away
     */
    public function __construct(
        public ErrorCode $code,
        public string $title,
        public string $summary,
        public string $example,
        public string $fix,
    ) {}
}
