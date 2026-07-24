<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter\Application;

use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Formatter\Application\PathsFormatter;
use Phel\Formatter\Domain\FormatterInterface;
use Phel\Formatter\Domain\IO\FileIoInterface;
use Phel\Formatter\Domain\PathFilterInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function sprintf;

final class PathsFormatterTest extends TestCase
{
    public function test_lexer_failure_on_one_file_does_not_abort_the_batch(): void
    {
        $io = $this->fileIo(['a.phel' => '(a', 'b.phel' => '(b)']);
        $formatter = $this->formatterThrowing('a.phel', new LexerValueException('bad token'));

        $output = new BufferedOutput();
        $changed = new PathsFormatter(
            $this->commandFacade(),
            $formatter,
            $this->pathFilter(['a.phel', 'b.phel']),
            $io,
        )->format(['ignored'], $output);

        self::assertSame(['b.phel'], $changed);
        self::assertStringContainsString('bad token', $output->fetch());
    }

    public function test_write_failure_propagates_instead_of_being_swallowed(): void
    {
        $io = new class implements FileIoInterface {
            public function checkIfValid(string $filename): void {}

            public function getContents(string $filename): string
            {
                return '(a)';
            }

            public function putContents(string $filename, string $data): void
            {
                throw new RuntimeException(sprintf('Unable to write file "%s".', $filename));
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to write file "a.phel".');

        new PathsFormatter(
            $this->commandFacade(),
            $this->formatterThrowing(null, null),
            $this->pathFilter(['a.phel']),
            $io,
        )->format(['ignored'], new BufferedOutput());
    }

    /**
     * @param array<string, string> $contents
     */
    private function fileIo(array $contents): FileIoInterface
    {
        return new class($contents) implements FileIoInterface {
            /**
             * @param array<string, string> $contents
             */
            public function __construct(private array $contents) {}

            public function checkIfValid(string $filename): void {}

            public function getContents(string $filename): string
            {
                return $this->contents[$filename] ?? '';
            }

            public function putContents(string $filename, string $data): void {}
        };
    }

    /**
     * Formats by appending a newline (so every file counts as "changed"),
     * except for $failingPath which raises $exception.
     */
    private function formatterThrowing(?string $failingPath, ?LexerValueException $exception): FormatterInterface
    {
        return new class($failingPath, $exception) implements FormatterInterface {
            public function __construct(
                private readonly ?string $failingPath,
                private readonly ?LexerValueException $exception,
            ) {}

            public function format(string $string, string $source = self::DEFAULT_SOURCE): string
            {
                if ($source === $this->failingPath && $this->exception instanceof LexerValueException) {
                    throw $this->exception;
                }

                return $string . "\n";
            }
        };
    }

    /**
     * @param list<string> $paths
     */
    private function pathFilter(array $paths): PathFilterInterface
    {
        return new class($paths) implements PathFilterInterface {
            /**
             * @param list<string> $paths
             */
            public function __construct(private readonly array $paths) {}

            public function filterPaths(array $paths): array
            {
                return $this->paths;
            }
        };
    }

    private function commandFacade(): CommandFacadeInterface
    {
        $facade = $this->createStub(CommandFacadeInterface::class);
        $facade->method('writeStackTrace')
            ->willReturnCallback(static function (OutputInterface $output, Throwable $e): void {
                $output->writeln($e->getMessage());
            });

        return $facade;
    }
}
