<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use PHPUnit\Framework\TestCase;

use function defined;
use function end;
use function explode;
use function file_get_contents;
use function preg_match_all;
use function sprintf;
use function str_contains;

/**
 * Every `*SetList::CONSTANT` named in `rector.php` has to exist in the
 * installed Rector.
 *
 * Rector removes set constants between minor releases, and `rector.php` names
 * them as PHP constants, so a removed one is a fatal `Undefined constant` that
 * takes down the whole `composer test-quality` run rather than reporting a
 * findable error. It has happened twice: `SetList::STRICT_BOOLEANS` vanished in
 * Rector 2.6, and `PHPUnitSetList::PHPUNIT_100` is absent from some
 * `rector-phpunit` builds, which is #3133.
 *
 * A unit test catches it as a named failure on the machine that has the
 * offending version, instead of as a stack trace from a quality tool.
 */
final class RectorSetListConstantsTest extends TestCase
{
    private const string CONFIG = __DIR__ . '/../../../../rector.php';

    public function test_every_set_list_constant_named_in_rector_config_exists(): void
    {
        $source = (string) file_get_contents(self::CONFIG);

        $imports = $this->importedClassesByShortName($source);

        preg_match_all('/\b(\w*SetList)::(\w+)\b/', $source, $matches, PREG_SET_ORDER);
        self::assertNotEmpty($matches, 'No set list constants found in rector.php; has the config moved?');

        foreach ($matches as [$reference, $shortName, $constant]) {
            self::assertArrayHasKey(
                $shortName,
                $imports,
                sprintf('%s is used in rector.php but not imported', $shortName),
            );

            self::assertTrue(
                defined($imports[$shortName] . '::' . $constant),
                sprintf(
                    '%s does not exist in the installed Rector. A removed set constant is a fatal error '
                    . 'that aborts `composer test-quality`, so it has to be replaced, not left in place.',
                    $reference,
                ),
            );
        }
    }

    /**
     * Short class name to fully qualified name, for the `use` statements the
     * config declares. Only these can appear as `Foo::BAR` in the file.
     *
     * @return array<string, string>
     */
    private function importedClassesByShortName(string $source): array
    {
        preg_match_all('/^use ([^;]+);$/m', $source, $useMatches);

        $imports = [];
        foreach ($useMatches[1] as $fqn) {
            if (str_contains($fqn, ' as ')) {
                continue;
            }

            $parts = explode('\\', $fqn);
            $imports[end($parts)] = $fqn;
        }

        return $imports;
    }
}
