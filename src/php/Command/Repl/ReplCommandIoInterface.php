<?php

declare(strict_types=1);

namespace Phel\Command\Repl;

interface ReplCommandIoInterface
{
    public function readHistory(): void;

    public function addHistory(string $line): void;

    public function readline(?string $prompt = null): ?string;

    public function write(string $string = ''): void;

    public function writeln(string $string = ''): void;

    public function isBracketedPasteSupported(): bool;
}
