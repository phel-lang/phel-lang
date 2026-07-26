<?php

declare(strict_types=1);

namespace PhelTest\Integration\Api;

use Phel;
use Phel\Api\ApiConfig;
use Phel\Api\ApiFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Shared\Api\PhelFunction;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

use function count;
use function implode;
use function in_array;
use function sprintf;
use function strlen;
use function trim;

/**
 * Ends the public-symbol documentation audit with a check instead of another
 * sweep.
 *
 * 60+ core functions were undocumented until recently and 34 `:example` blocks
 * documented output Phel does not print. Both were found by hand, twice. A
 * recurring discovery is a missing test, so this is the test.
 *
 * `:doc` is required outright. `:example` is a ratchet: too many are missing today
 * to require one, so the count is pinned and can only go down.
 */
final class PublicSymbolDocumentationTest extends TestCase
{
    /**
     * The one public symbol without a docstring, and why it stays that way.
     *
     * `*build-mode*` is not defined in Phel at all: `BuildFacade` writes it
     * straight into the `phel.core` registry, deliberately without metadata,
     * because that write is on the `(load ...)` hot path and building a metadata
     * map there costs more than the flag is worth. It is an internal flag that
     * happens to be visible to `phel doc`.
     *
     * @var list<string>
     */
    private const array UNDOCUMENTED_BY_DESIGN = [
        'phel.core/*build-mode*',
    ];

    /**
     * Public definitions with no `:example`, as of this commit.
     *
     * A ratchet, not a target: lower it when examples are added, never raise it
     * to make a red build green. Adding a public definition without an example
     * fails here, which is the point at which writing one is cheapest.
     */
    private const int MAX_DEFINITIONS_WITHOUT_AN_EXAMPLE = 128;

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_every_public_definition_has_a_docstring(): void
    {
        $undocumented = [];

        foreach ($this->publicDefinitions() as $function) {
            $name = sprintf('phel.%s/%s', $function->namespace, $this->bareName($function));

            if (in_array($name, self::UNDOCUMENTED_BY_DESIGN, true)) {
                continue;
            }

            if (trim($function->description) !== '') {
                continue;
            }

            $undocumented[] = $name;
        }

        self::assertSame(
            [],
            $undocumented,
            sprintf(
                "%d public definition(s) have no `:doc`.\nEvery public definition needs one; "
                . "see the conventions in .claude/rules/phel.md:\n  %s",
                count($undocumented),
                implode("\n  ", $undocumented),
            ),
        );
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_the_number_of_definitions_without_an_example_only_goes_down(): void
    {
        $withoutExample = [];

        foreach ($this->publicDefinitions() as $function) {
            // `:example` rides in the meta bag rather than being a first-class
            // property, and is absent (not empty) when the definition has none.
            if (trim((string) ($function->meta['example'] ?? '')) !== '') {
                continue;
            }

            $withoutExample[] = sprintf('phel.%s/%s', $function->namespace, $this->bareName($function));
        }

        $count = count($withoutExample);

        self::assertLessThanOrEqual(
            self::MAX_DEFINITIONS_WITHOUT_AN_EXAMPLE,
            $count,
            sprintf(
                "%d public definitions have no `:example`, over the ratchet of %d.\n"
                . 'Add an `:example` to what this change introduced rather than raising the limit.',
                $count,
                self::MAX_DEFINITIONS_WITHOUT_AN_EXAMPLE,
            ),
        );

        // Ratchets that are never tightened stop meaning anything, so a run that
        // beats the pin says so out loud instead of passing quietly.
        self::assertGreaterThan(
            self::MAX_DEFINITIONS_WITHOUT_AN_EXAMPLE - 1,
            $count,
            sprintf(
                'Only %d definitions lack an `:example` now. Lower MAX_DEFINITIONS_WITHOUT_AN_EXAMPLE to %d.',
                $count,
                $count,
            ),
        );
    }

    /**
     * @return list<PhelFunction>
     */
    private function publicDefinitions(): array
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        return new ApiFacade()->getPhelFunctions(ApiConfig::allNamespaces());
    }

    private function bareName(PhelFunction $function): string
    {
        $prefix = $function->namespace . '/';

        return str_starts_with($function->name, $prefix)
            ? substr($function->name, strlen($prefix))
            : $function->name;
    }
}
