<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\AnalyzePersistentList;
use Phel\Compiler\Domain\Deprecation\SupersededFormDeprecator;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_get_contents;
use function preg_match;
use function preg_match_all;
use function sort;

/**
 * `docs/spec/language-surface.md` claims the special-form list is closed for 1.x.
 * A claim a document makes about code is worth exactly as much as the check that
 * keeps them together, so this parses the table out of the page and compares it
 * against the analyzer's own dispatch registry.
 *
 * Adding a special form therefore fails the build until the spec is updated too,
 * which is the moment somebody has to decide whether the addition is allowed
 * inside the major. Removing or renaming one is a break, and fails the same way.
 */
final class LanguageSurfaceSpecTest extends TestCase
{
    public function test_the_spec_lists_exactly_the_registered_special_forms(): void
    {
        $documented = $this->specialFormsDocumentedInTheSpec();
        $registered = $this->registeredSpecialFormNames();

        self::assertSame(
            $registered,
            $documented,
            "docs/spec/language-surface.md no longer matches the analyzer's special-form registry.\n"
            . 'Update the table in section 2, and decide whether the change is allowed inside the major.',
        );
    }

    public function test_the_spec_lists_exactly_the_forms_that_warn_as_deprecated(): void
    {
        $documented = $this->deprecatedFormsDocumentedInTheSpec();
        $warning = SupersededFormDeprecator::supersededFormNames();
        sort($warning);

        self::assertSame(
            $warning,
            $documented,
            "docs/spec/language-surface.md no longer matches SupersededFormDeprecator.\n"
            . 'Update the "Deprecated inside 1.x" table in section 2.',
        );
    }

    /**
     * The first column of the table in section 2, where every cell is a single
     * form wrapped in backticks.
     *
     * @return list<string>
     */
    private function specialFormsDocumentedInTheSpec(): array
    {
        $spec = (string) file_get_contents(dirname(__DIR__, 4) . '/docs/spec/language-surface.md');

        preg_match_all('/^\| `([^`]+)` \| (?:core|interop|namespacing|type definition) \|/m', $spec, $matches);

        $forms = $matches[1];
        sort($forms);

        return $forms;
    }

    /**
     * The first column of the "Deprecated inside 1.x" table, scoped to that
     * subsection so the closed-list table above it cannot leak in.
     *
     * @return list<string>
     */
    private function deprecatedFormsDocumentedInTheSpec(): array
    {
        $spec = (string) file_get_contents(dirname(__DIR__, 4) . '/docs/spec/language-surface.md');

        preg_match('/^### Deprecated inside 1\.x$(.*?)^### /ms', $spec, $section);
        preg_match_all('/^\| `([^`]+)` \| /m', $section[1] ?? '', $matches);

        $forms = $matches[1];
        sort($forms);

        return $forms;
    }

    /**
     * @return list<string>
     */
    private function registeredSpecialFormNames(): array
    {
        $listAnalyzer = new AnalyzePersistentList(
            $this->createStub(AnalyzerInterface::class),
            assertsEnabled: true,
        );

        $names = $listAnalyzer->specialFormNames();
        sort($names);

        return $names;
    }
}
