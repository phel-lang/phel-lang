<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\AnalyzePersistentList;
use Phel\Compiler\Domain\Deprecation\SupersededFormRejector;
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

    public function test_the_spec_lists_exactly_the_forms_that_are_rejected(): void
    {
        $documented = $this->rejectedFormsDocumentedInTheSpec();
        $rejected = SupersededFormRejector::supersededFormNames();
        sort($rejected);

        self::assertSame(
            $rejected,
            $documented,
            "docs/spec/language-surface.md no longer matches SupersededFormRejector.\n"
            . 'Update the "Rejected as source from <version>" table in section 2.',
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
     * The first column of the "Rejected as source from <version>" table, scoped
     * to that subsection so the closed-list table above it cannot leak in.
     *
     * The version in the heading is matched loosely on purpose: pinning it meant
     * that correcting the version silently emptied this list instead of failing
     * loudly, which is the opposite of what the guard is for.
     *
     * @return list<string>
     */
    private function rejectedFormsDocumentedInTheSpec(): array
    {
        $spec = (string) file_get_contents(dirname(__DIR__, 4) . '/docs/spec/language-surface.md');

        preg_match(
            '/^### Rejected as source from [0-9]+\.[0-9]+\.[0-9]+$(.*?)(?=^#{2,3} |\z)/ms',
            $spec,
            $section,
        );

        self::assertArrayHasKey(
            1,
            $section,
            'docs/spec/language-surface.md has no readable "### Rejected as source from <version>" '
            . 'section. Either the heading is missing or renamed, or the section is not terminated '
            . 'by a following heading or end of file.',
        );
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
