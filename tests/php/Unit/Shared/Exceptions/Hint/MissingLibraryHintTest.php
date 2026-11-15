<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\Exceptions\Hint;

use Phel\Build\Domain\Extractor\MissingNamespaceException;
use Phel\Lang\SourceLocation;
use Phel\Shared\Exceptions\Hint\MissingLibraryHint;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MissingLibraryHintTest extends TestCase
{
    public function test_points_a_missing_namespace_at_composer(): void
    {
        $hint = new MissingLibraryHint();
        $e = $this->missing("Cannot find namespace 'acme.http' required by 'app.main'.");

        self::assertTrue($hint->appliesTo($e));
        self::assertSame(
            "if 'acme.http' comes from a library, install it with composer require <vendor/package>. Phel reads libraries from the vendor directory.",
            $hint->hint($e),
        );
    }

    public function test_stays_out_of_the_way_of_a_spelling_suggestion(): void
    {
        self::assertFalse(new MissingLibraryHint()->appliesTo(
            $this->missing("Cannot find namespace 'app.utl' required by 'app.main'. Did you mean 'app.util'?"),
        ));
    }

    public function test_skips_phel_and_clojure_namespaces(): void
    {
        $hint = new MissingLibraryHint();

        self::assertFalse($hint->appliesTo($this->missing("Cannot find namespace 'phel.nope' required by 'app.main'.")));
        self::assertFalse($hint->appliesTo($this->missing("Cannot find namespace 'clojure.nope' required by 'app.main'.")));
    }

    public function test_names_the_package_of_an_official_library(): void
    {
        $hint = new MissingLibraryHint();
        $e = $this->missing("Cannot find namespace 'phel.pdo' required by 'app.main'. Did you mean 'phel.edn'?");

        self::assertTrue($hint->appliesTo($e));
        self::assertSame("'phel.pdo' comes from phel-lang/phel-pdo. Install it with composer require phel-lang/phel-pdo.", $hint->hint($e));
    }

    public function test_a_missing_child_of_an_official_library_keeps_the_spelling_suggestion(): void
    {
        self::assertFalse(new MissingLibraryHint()->appliesTo(
            $this->missing("Cannot find namespace 'phel.sql.x' required by 'app.main'. Did you mean 'phel.sql'?"),
        ));
    }

    public function test_applies_without_a_located_error_as_in_the_repl(): void
    {
        $e = new RuntimeException("Cannot find namespace 'acme.ev' required by 'app.e'. Check the spelling.");

        self::assertTrue(new MissingLibraryHint()->appliesTo($e));
    }

    public function test_keeps_a_quote_inside_the_namespace(): void
    {
        self::assertStringStartsWith(
            "if 'acme.it's' comes from a library",
            new MissingLibraryHint()->hint($this->missing("Cannot find namespace 'acme.it's' required by 'app.main'.")),
        );
    }

    public function test_ignores_other_errors(): void
    {
        self::assertFalse(new MissingLibraryHint()->appliesTo(new RuntimeException("Cannot resolve symbol 'acme.http'")));
    }

    private function missing(string $message): MissingNamespaceException
    {
        $location = new SourceLocation('src/main.phel', 1, 1);

        return MissingNamespaceException::at($message, $location, $location, '');
    }
}
