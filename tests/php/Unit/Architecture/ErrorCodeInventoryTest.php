<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Exceptions\ErrorCodeCatalog;
use Phel\Shared\Exceptions\ErrorCodeExplanation;
use PhelTest\Support\ErrorDocs;
use PHPUnit\Framework\TestCase;

use function array_diff;
use function array_keys;
use function array_map;
use function array_values;
use function basename;
use function glob;
use function implode;
use function sort;
use function sprintf;
use function trim;

/**
 * `ErrorCode` is a user-visible surface: a code the user reads must name an
 * error Phel actually raises, and must be explainable. These tests keep the
 * enum, the catalog and the pages under `docs/errors/` in step.
 */
final class ErrorCodeInventoryTest extends TestCase
{
    use ScansPhpSourcesTrait;

    private const string ENUM_FILE = 'Shared/Exceptions/ErrorCode.php';

    public function test_every_error_code_has_a_production_caller(): void
    {
        $sources = $this->phpFilesIn('src/php');
        unset($sources[self::ENUM_FILE]);

        $orphans = [];

        foreach (ErrorCode::cases() as $case) {
            $pattern = sprintf('/\bErrorCode::%s\b/', $case->name);

            foreach ($sources as $contents) {
                if (preg_match($pattern, $contents) === 1) {
                    continue 2;
                }
            }

            $orphans[] = sprintf('%s (%s)', $case->name, $case->value);
        }

        self::assertSame([], $orphans, sprintf(
            "These error codes have no production caller: %s.\nWire each one to the error it names or delete the case.",
            implode(', ', $orphans),
        ));
    }

    /**
     * A code with no catalog entry is a code nobody can look up: `phel explain`
     * has nothing to print for it and it reaches no page. An entry with no case
     * is prose about an error Phel never raises.
     */
    public function test_every_error_code_has_a_catalog_entry(): void
    {
        $documented = array_map(
            static fn(ErrorCodeExplanation $explanation): string => $explanation->code->value,
            ErrorCodeCatalog::all(),
        );
        $declared = array_map(static fn(ErrorCode $case): string => $case->value, ErrorCode::cases());

        $missing = array_values(array_diff($declared, $documented));
        self::assertSame([], $missing, sprintf(
            "These error codes have no catalog entry: %s.\nAdd one to ErrorCodeCatalog, then run `%s`.",
            implode(', ', $missing),
            ErrorDocs::UPDATE_COMMAND,
        ));

        $extra = array_values(array_diff($documented, $declared));
        self::assertSame([], $extra, sprintf(
            "These catalog entries name no error code: %s.\nAdd the case to ErrorCode or drop the entry.",
            implode(', ', $extra),
        ));

        foreach (ErrorCode::cases() as $case) {
            $explanation = ErrorCodeCatalog::explain($case);

            self::assertSame($case, $explanation->code, sprintf(
                'ErrorCodeCatalog::explain(%s) returned the entry for %s.',
                $case->name,
                $explanation->code->name,
            ));

            foreach (['title' => $explanation->title, 'summary' => $explanation->summary, 'fix' => $explanation->fix] as $part => $value) {
                self::assertNotSame('', trim($value), sprintf(
                    'The catalog entry for %s (%s) has an empty %s. Every code needs all three to be worth looking up.',
                    $case->name,
                    $case->value,
                    $part,
                ));
            }
        }
    }

    /**
     * The pages are generated, never written: rendering the catalog again has to
     * reproduce what is committed, byte for byte, or the docs are describing a
     * catalog that no longer exists.
     */
    public function test_the_generated_pages_match_the_catalog(): void
    {
        $docs = ErrorDocs::fromRepositoryRoot(ErrorDocs::repositoryRoot());
        $directory = $docs->outputDirectory();
        $expected = $docs->render();

        $committed = array_map(basename(...), glob($directory . '/*.md') ?: []);
        sort($committed);

        $names = array_keys($expected);
        sort($names);

        self::assertSame($names, $committed, sprintf(
            "docs/errors/ does not hold the pages the catalog describes.\nRun `%s`.",
            ErrorDocs::UPDATE_COMMAND,
        ));

        foreach ($expected as $name => $contents) {
            self::assertStringEqualsFile($directory . '/' . $name, $contents, sprintf(
                "docs/errors/%s is out of date with ErrorCodeCatalog.\nRun `%s`.",
                $name,
                ErrorDocs::UPDATE_COMMAND,
            ));
        }
    }
}
