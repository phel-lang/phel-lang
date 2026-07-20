<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

use Phel\Shared\Facade\CommandFacadeInterface;

/**
 * Fallback REPL I/O when the readline extension is not available.
 * Uses fgets(STDIN) for input — no tab completion or history persistence.
 */
final readonly class ReplCommandFallbackIo implements ReplCommandIoInterface
{
    use ReplOutputTrait;

    public function __construct(
        private CommandFacadeInterface $commandFacade,
        private ReplErrorFormatter $errorFormatter,
    ) {}

    public function readHistory(): void {}

    public function addHistory(string $line): void {}

    public function readline(?string $prompt = null): ?string
    {
        if ($prompt !== null) {
            $this->write($prompt);
        }

        $line = fgets(STDIN);

        if ($line === false) {
            return null;
        }

        return rtrim($line, "\n\r");
    }

    public function isBracketedPasteSupported(): bool
    {
        return false;
    }
}
