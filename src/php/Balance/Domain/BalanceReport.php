<?php

declare(strict_types=1);

namespace Phel\Balance\Domain;

use function array_reverse;
use function implode;
use function sprintf;

/**
 * What one scan of one file found.
 *
 * @internal
 */
final readonly class BalanceReport
{
    /**
     * @param list<OpenDelimiter>    $unclosed               outermost first
     * @param list<UnexpectedCloser> $unexpectedClosers
     * @param int|null               $unterminatedStringLine line of the first
     *                                                       unterminated string
     *                                                       literal, if any
     * @param bool                   $endsInLineComment      whether the last
     *                                                       meaningful token is a
     *                                                       `;` comment
     */
    public function __construct(
        public array $unclosed,
        public array $unexpectedClosers,
        public ?int $unterminatedStringLine,
        public bool $endsInLineComment,
    ) {}

    public function isBalanced(): bool
    {
        return $this->unclosed === []
            && $this->unexpectedClosers === []
            && $this->unterminatedStringLine === null;
    }

    /**
     * A surplus or mismatched closer is not a missing one, and an unterminated
     * string makes the whole delimiter count a fiction: the lexer reads
     * `(println "hi) (there` as an atom `"hi` followed by a real `)`, so the
     * closer the author meant as string content already counted. Appending
     * anything there writes a file that is still broken, differently.
     */
    public function isRepairable(): bool
    {
        return $this->unclosed !== []
            && $this->unexpectedClosers === []
            && $this->unterminatedStringLine === null;
    }

    /**
     * The closers to append, innermost level first.
     */
    public function missingClosers(): string
    {
        $closers = '';
        foreach (array_reverse($this->unclosed) as $open) {
            $closers .= $open->closerText;
        }

        return $closers;
    }

    public function unrepairableReason(): ?string
    {
        if ($this->unterminatedStringLine !== null) {
            return 'unterminated string literal on line ' . $this->unterminatedStringLine;
        }

        if ($this->unexpectedClosers !== []) {
            $parts = [];
            foreach ($this->unexpectedClosers as $closer) {
                $parts[] = $closer->openDelimiter instanceof OpenDelimiter
                    ? sprintf("line %d: '%s' closes '%s' opened on line %d", $closer->line, $closer->closerText, $closer->openDelimiter->openerText, $closer->openDelimiter->line)
                    : sprintf("line %d: '%s' closes nothing", $closer->line, $closer->closerText);
            }

            return implode('; ', $parts);
        }

        return null;
    }
}
