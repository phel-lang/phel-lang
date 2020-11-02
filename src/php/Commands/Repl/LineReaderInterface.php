<?php

declare(strict_types=1);

namespace Phel\Commands\Repl;

interface LineReaderInterface
{
    public function readHistory(): void;

    public function addHistory(string $line): void;

    public function readline(?string $prompt = null): ?string;
}
