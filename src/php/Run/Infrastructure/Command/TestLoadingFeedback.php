<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

/**
 * Startup feedback for `phel test`: discovery and namespace loading can take
 * several seconds (cold cache: tens of seconds) and used to print nothing.
 *
 * Everything goes to STDERR so machine-readable reporters on stdout
 * (`--reporter=tap`, junit-xml) stay parseable. On an interactive terminal the
 * loading counter rewrites one line in place; on a non-decorated stream it
 * collapses to a single static line so CI logs are not flooded.
 */
final class TestLoadingFeedback
{
    private const string CLEAR_LINE = "\r\033[2K";

    private int $total = 0;

    private int $current = 0;

    public function __construct(
        private readonly OutputInterface $output,
    ) {}

    /**
     * Progress belongs on the error stream; when the caller's output has none
     * (unit tests, exotic SAPIs), stay silent rather than corrupt stdout.
     */
    public static function fromOutput(OutputInterface $output): self
    {
        if ($output instanceof ConsoleOutputInterface) {
            return new self($output->getErrorOutput());
        }

        return new self(new NullOutput());
    }

    public function discovering(): void
    {
        $this->output->writeln('<comment>Discovering tests...</comment>');
    }

    public function startLoading(int $total): void
    {
        $this->total = $total;
        $this->current = 0;
        if (!$this->output->isDecorated()) {
            $this->output->writeln(sprintf('Loading %d namespace(s)...', $total));
        }
    }

    public function advance(string $namespace): void
    {
        ++$this->current;
        if ($this->output->isDecorated()) {
            $this->output->write(sprintf(
                '%sLoading namespaces %d/%d (%s)',
                self::CLEAR_LINE,
                $this->current,
                $this->total,
                $namespace,
            ));
        }
    }

    public function finishLoading(): void
    {
        if ($this->output->isDecorated()) {
            $this->output->write(self::CLEAR_LINE);
        }
    }
}
