<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use PhelTest\Support\PublicApiSurface;
use PHPUnit\Framework\TestCase;

use function count;
use function sprintf;
use function str_contains;

/**
 * `@internal` is how the public/internal split in `docs/stability.md` reaches a
 * consumer's IDE and static analyser, which never read the prose.
 *
 * {@see PublicApiSurfaceTest} pins what the public surface *contains*. This pins
 * the complement: every class it excludes says so in its own docblock. Without
 * it, a new internal class is indistinguishable from a public one at the call
 * site, and the annotation set decays one file at a time.
 *
 * Both tests read the same rules from {@see PublicApiSurface}, so the snapshot,
 * the annotations and the policy page cannot disagree.
 */
final class InternalAnnotationTest extends TestCase
{
    use ScansPhpSourcesTrait;

    public function test_every_internal_class_is_annotated_internal(): void
    {
        $missing = [];

        foreach ($this->phpFilesIn('src/php') as $relative => $contents) {
            $className = 'Phel\\' . str_replace(['/', '.php'], ['\\', ''], $relative);

            if ($this->surface()->isPublicSymbol($className)) {
                continue;
            }

            if (str_contains($contents, '@internal')) {
                continue;
            }

            $missing[] = $relative;
        }

        self::assertSame(
            [],
            $missing,
            sprintf(
                "%d internal class(es) are missing an `@internal` annotation.\n"
                . "Add `@internal` to the class docblock, or, if the class really is part of the\n"
                . "public surface, extend the rules in tests/php/Support/PublicApiSurface.php and\n"
                . "say so in docs/stability.md:\n  %s",
                count($missing),
                implode("\n  ", $missing),
            ),
        );
    }

    /**
     * The reverse mistake is quieter and worse: a public class marked internal
     * tells every consumer not to use the thing semver exists to protect.
     */
    public function test_no_public_class_is_annotated_internal(): void
    {
        $wronglyMarked = [];

        foreach ($this->phpFilesIn('src/php') as $relative => $contents) {
            $className = 'Phel\\' . str_replace(['/', '.php'], ['\\', ''], $relative);

            if (!$this->surface()->isPublicSymbol($className)) {
                continue;
            }

            if (!str_contains($contents, '@internal')) {
                continue;
            }

            $wronglyMarked[] = $relative;
        }

        self::assertSame([], $wronglyMarked, 'Public API classes must not be marked `@internal`.');
    }

    private function surface(): PublicApiSurface
    {
        return PublicApiSurface::fromRepositoryRoot(PublicApiSurface::repositoryRoot());
    }
}
