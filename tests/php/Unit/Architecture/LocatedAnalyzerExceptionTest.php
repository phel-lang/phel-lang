<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use Phel\Shared\Exceptions\AbstractLocatedException;
use PHPUnit\Framework\TestCase;

use function count;
use function sprintf;

/**
 * Only an {@see AbstractLocatedException} reaches the
 * printer with a snippet, a caret and an error code. An analyzer error that
 * extends a plain exception instead prints one line and a wall of internal
 * frames, which is what #3267 fixed for a duplicate definition. The rest of
 * the directory is pinned here so the next one cannot arrive unnoticed.
 */
final class LocatedAnalyzerExceptionTest extends TestCase
{
    use ScansPhpSourcesTrait;

    private const string EXCEPTIONS_DIR = 'src/php/Compiler/Domain/Analyzer/Exceptions';

    private const string EXCEPTIONS_NAMESPACE = 'Phel\\Compiler\\Domain\\Analyzer\\Exceptions\\';

    /**
     * Analyzer exceptions that are deliberately not located, with the reason.
     *
     * @var array<string, string>
     */
    private const array UNLOCATED = [
        'GlobalEnvironmentAlreadyInitializedException.php' => 'raised by a host caller misusing the environment API, never by a Phel source form, so there is no location to point at',
    ];

    public function test_every_analyzer_exception_is_located(): void
    {
        $unlocated = [];

        foreach (array_keys($this->phpFilesIn(self::EXCEPTIONS_DIR)) as $relative) {
            if (isset(self::UNLOCATED[$relative])) {
                continue;
            }

            $exceptionClass = self::EXCEPTIONS_NAMESPACE . str_replace(['/', '.php'], ['\\', ''], $relative);

            // By class rather than by `extends` line, so an exception reaching
            // the base through an intermediate one still counts as located.
            if (is_subclass_of($exceptionClass, AbstractLocatedException::class)) {
                continue;
            }

            $unlocated[] = $relative;
        }

        self::assertSame(
            [],
            $unlocated,
            sprintf(
                "%d analyzer exception(s) do not extend AbstractLocatedException, so they print\n"
                . "without a snippet, a caret or an error code.\n"
                . "Extend it and pass the source location, or, if the error genuinely has no\n"
                . "location, add the class to self::UNLOCATED with the reason:\n  %s",
                count($unlocated),
                implode("\n  ", $unlocated),
            ),
        );
    }

    public function test_the_unlocated_allowlist_names_only_existing_exceptions(): void
    {
        $files = $this->phpFilesIn(self::EXCEPTIONS_DIR);

        foreach (array_keys(self::UNLOCATED) as $allowed) {
            self::assertArrayHasKey($allowed, $files, $allowed . ' no longer exists; drop it from the allowlist.');
        }
    }
}
