<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter\Application;

use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Formatter\Application\PathsFormatter;
use Phel\Formatter\Domain\FormatterInterface;
use Phel\Formatter\Domain\IO\ValidatedFileIoInterface;
use Phel\Formatter\Domain\PathFilterInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function getcwd;
use function sprintf;

final class PathsFormatterTest extends TestCase
{
    public function test_lexer_failure_on_one_file_does_not_abort_the_batch(): void
    {
        $io = $this->fileIo(['a.phel' => '(a', 'b.phel' => '(b)']);
        $formatter = $this->formatterThrowing('a.phel', new LexerValueException('bad token'));

        $output = new BufferedOutput();
        $result = new PathsFormatter(
            $this->commandFacade(),
            $formatter,
            $this->pathFilter(['a.phel', 'b.phel']),
            $io,
        )->format(['ignored'], $output);

        self::assertSame(['b.phel'], $result->changedPaths());
        self::assertStringContainsString('bad token', $output->fetch());
    }

    public function test_unformattable_file_is_reported_as_failed(): void
    {
        $io = $this->fileIo(['a.phel' => '(a', 'b.phel' => '(b)']);
        $formatter = $this->formatterThrowing('a.phel', new LexerValueException('bad token'));

        $result = new PathsFormatter(
            $this->commandFacade(),
            $formatter,
            $this->pathFilter(['a.phel', 'b.phel']),
            $io,
        )->format(['ignored'], new BufferedOutput());

        self::assertTrue($result->hasFailures());
        self::assertSame(['a.phel'], $result->failedPaths());
    }

    public function test_write_failure_propagates_instead_of_being_swallowed(): void
    {
        $io = new class() implements ValidatedFileIoInterface {
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
     * A generated data file or a vendored tree beside its consumers can be
     * left alone (#3233): patterns are `fnmatch`ed against the path as
     * discovered and against its form relative to the working directory, and
     * `*` spans directories, as in `phel lint`'s exclude.
     */
    public function test_excluded_paths_are_skipped_and_land_in_no_bucket(): void
    {
        $io = $this->fileIo(['src/a.phel' => '(a)', 'src/gen/table_data.phel' => '(t)', 'src/b.phel' => '(b)']);

        $result = new PathsFormatter(
            $this->commandFacade(),
            $this->formatterThrowing(null, null),
            $this->pathFilter(['src/a.phel', 'src/gen/table_data.phel', 'src/b.phel']),
            $io,
        )->format(['src'], new BufferedOutput(), false, ['src/*_data.phel']);

        self::assertSame(['src/a.phel', 'src/b.phel'], $result->changedPaths());
        self::assertSame([], $result->failedPaths());
    }

    public function test_an_absolute_discovered_path_matches_a_pattern_relative_to_the_working_directory(): void
    {
        $absolute = getcwd() . '/src/gen/table_data.phel';
        $io = $this->fileIo([$absolute => '(t)']);

        $result = new PathsFormatter(
            $this->commandFacade(),
            $this->formatterThrowing(null, null),
            $this->pathFilter([$absolute]),
            $io,
        )->format([$absolute], new BufferedOutput(), false, ['src/gen/*']);

        self::assertSame([], $result->changedPaths());
    }

    public function test_an_excluded_file_is_named_under_verbose_output(): void
    {
        $io = $this->fileIo(['src/gen/table_data.phel' => '(t)']);
        $output = new BufferedOutput(OutputInterface::VERBOSITY_VERBOSE);

        new PathsFormatter(
            $this->commandFacade(),
            $this->formatterThrowing(null, null),
            $this->pathFilter(['src/gen/table_data.phel']),
            $io,
        )->format(['src/gen/table_data.phel'], $output, false, ['*_data.phel']);

        self::assertStringContainsString('Excluded: src/gen/table_data.phel', $output->fetch());
    }

    /**
     * @param array<string, string> $contents
     */
    private function fileIo(array $contents): ValidatedFileIoInterface
    {
        return new class($contents) implements ValidatedFileIoInterface {
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
        return new readonly class($failingPath, $exception) implements FormatterInterface {
            public function __construct(
                private ?string $failingPath,
                private ?LexerValueException $exception,
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
        return new readonly class($paths) implements PathFilterInterface {
            /**
             * @param list<string> $paths
             */
            public function __construct(private array $paths) {}

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
