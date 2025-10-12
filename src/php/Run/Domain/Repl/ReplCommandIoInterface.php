<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Compiler\Domain\Parser\ReadModel\CodeSnippet;
use Throwable;

interface ReplCommandIoInterface
{
    public function readHistory(): void;

    public function addHistory(string $line): void;

    public function readline(?string $prompt = null): ?string;

    public function writeStackTrace(Throwable $e): void;

    public function writeLocatedException(AbstractLocatedException $e, CodeSnippet $codeSnippet): void;

    /**
     * @psalm-taint-escape html
     * @psalm-taint-escape has_quotes
     *
     * @psalm-suppress TaintedHtml
     * @psalm-suppress TaintedTextWithQuotes
     */
    public function write(string $string = ''): void;

    /**
     * @psalm-taint-escape html
     * @psalm-taint-escape has_quotes
     *
     * @psalm-suppress TaintedHtml
     * @psalm-suppress TaintedTextWithQuotes
     */
    public function writeln(string $string = ''): void;

    public function isBracketedPasteSupported(): bool;
}
