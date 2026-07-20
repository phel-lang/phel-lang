<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

use Phel\Shared\Facade\ApiFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\ScalarCoercion;

use function count;

final readonly class ReplCommandSystemIo implements ReplCommandIoInterface
{
    use ReplOutputTrait;

    public function __construct(
        private string $historyFile,
        private CommandFacadeInterface $commandFacade,
        private ApiFacadeInterface $apiFacade,
        private ReplErrorFormatter $errorFormatter,
    ) {
        readline_completion_function(
            $this->completeWithInlineDoc(...),
        );
    }

    public function readHistory(): void
    {
        readline_clear_history();
        readline_read_history($this->historyFile);
    }

    public function addHistory(string $line): void
    {
        readline_add_history($line);
        readline_write_history($this->historyFile);
    }

    public function readline(?string $prompt = null): ?string
    {
        $line = readline($prompt);

        if ($line === false) {
            return null;
        }

        return $line;
    }

    public function isBracketedPasteSupported(): bool
    {
        $haystack = ScalarCoercion::toString(readline_info('library_version'));

        return stripos($haystack, 'editline') === false;
    }

    /**
     * Completion callback that, when the input resolves to a single candidate,
     * prints that candidate's one-line doc inline before readline redraws the
     * prompt — so Tab on a unique/focused symbol surfaces its signature + doc.
     *
     * @return list<string>
     */
    private function completeWithInlineDoc(string $input): array
    {
        $matches = $this->apiFacade->replComplete($input);

        if (count($matches) === 1) {
            $doc = $this->apiFacade->completionDoc($matches[0]);
            if ($doc !== null && $doc !== '') {
                echo PHP_EOL . $doc . PHP_EOL; // phpcs:ignore
            }
        }

        return $matches;
    }

}
