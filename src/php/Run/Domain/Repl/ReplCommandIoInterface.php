<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use Throwable;

/**
 * @internal
 */
interface ReplCommandIoInterface
{
    public function readHistory(): void;

    public function addHistory(string $line): void;

    public function readline(?string $prompt = null): ?string;

    /**
     * Renders the error report a user reads at the prompt: headline, hint, and
     * a trace whose internal frames are collapsed unless `--stack-trace` asks
     * for them.
     */
    public function writeReplError(Throwable $e, bool $showInternalFrames = false): void;

    public function writeLocatedException(AbstractLocatedException $e, CodeSnippet $codeSnippet): void;

    /**
     * @psalm-taint-escape html
     * @psalm-taint-escape has_quotes
     */
    public function write(string $string = ''): void;

    /**
     * @psalm-taint-escape html
     * @psalm-taint-escape has_quotes
     */
    public function writeln(string $string = ''): void;

    public function isBracketedPasteSupported(): bool;
}
