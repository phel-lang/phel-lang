<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\Environment;

use Phel;
use Phel\Compiler\Domain\Analyzer\Environment\DeprecatedDefinitionWarner;
use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use PhelTest\Support\CapturesDeprecationsTrait;
use PHPUnit\Framework\TestCase;

use function dirname;

final class DeprecatedDefinitionWarnerTest extends TestCase
{
    use CapturesDeprecationsTrait;

    protected function setUp(): void
    {
        $this->startCapturingDeprecations();
    }

    protected function tearDown(): void
    {
        $this->stopCapturingDeprecations();
    }

    public function test_warns_with_version_and_replacement(): void
    {
        $this->warner()->maybeWarn(
            'phel.core',
            $this->locatedSymbol('set-meta!', '/app/user.phel'),
            $this->meta(['deprecated' => '0.32.0', 'superseded-by' => 'with-meta']),
        );

        $captured = $this->capturedDeprecations();
        self::assertCount(1, $captured);
        self::assertStringContainsString("'phel.core/set-meta!'", $captured[0]);
        self::assertStringContainsString('/app/user.phel:1', $captured[0]);
        self::assertStringContainsString('(since 0.32.0)', $captured[0]);
        self::assertStringContainsString("Use 'with-meta' instead.", $captured[0]);
    }

    public function test_renders_non_version_metadata_as_a_reason(): void
    {
        $this->warner()->maybeWarn(
            'phel.test',
            $this->locatedSymbol('print-summary', '/app/user.phel'),
            $this->meta(['deprecated' => 'run-tests emits it already']),
        );

        $captured = $this->capturedDeprecations();
        self::assertCount(1, $captured);
        self::assertStringContainsString(': run-tests emits it already', $captured[0]);
    }

    public function test_warns_for_boolean_true_metadata_without_detail(): void
    {
        $this->warner()->maybeWarn(
            'my.app',
            $this->locatedSymbol('old-fn', '/app/user.phel'),
            $this->meta(['deprecated' => true]),
        );

        $captured = $this->capturedDeprecations();
        self::assertCount(1, $captured);
        self::assertStringContainsString("'my.app/old-fn' used at /app/user.phel:1 is deprecated.", $captured[0]);
    }

    public function test_stays_silent_when_warnings_are_disabled(): void
    {
        DeprecationWarnings::disable();

        $this->warner()->maybeWarn(
            'phel.core',
            $this->locatedSymbol('set-meta!', '/app/user.phel'),
            $this->meta(['deprecated' => '0.32.0']),
        );

        self::assertSame([], $this->capturedDeprecations());
    }

    public function test_stays_silent_for_a_definition_without_deprecated_metadata(): void
    {
        $this->warner()->maybeWarn(
            'phel.core',
            $this->locatedSymbol('with-meta', '/app/user.phel'),
            $this->meta(['doc' => 'Returns obj with meta attached.']),
        );

        self::assertSame([], $this->capturedDeprecations());
    }

    public function test_stays_silent_when_deprecated_metadata_is_false(): void
    {
        $this->warner()->maybeWarn(
            'phel.core',
            $this->locatedSymbol('with-meta', '/app/user.phel'),
            $this->meta(['deprecated' => false]),
        );

        self::assertSame([], $this->capturedDeprecations());
    }

    public function test_dedupes_repeated_use_in_the_same_file(): void
    {
        $warner = $this->warner();
        $meta = $this->meta(['deprecated' => '0.32.0']);

        $warner->maybeWarn('phel.core', $this->locatedSymbol('set-meta!', '/app/a.phel'), $meta);
        $warner->maybeWarn('phel.core', $this->locatedSymbol('set-meta!', '/app/a.phel'), $meta);

        self::assertCount(1, $this->capturedDeprecations());
    }

    public function test_warns_again_in_a_different_file(): void
    {
        $warner = $this->warner();
        $meta = $this->meta(['deprecated' => '0.32.0']);

        $warner->maybeWarn('phel.core', $this->locatedSymbol('set-meta!', '/app/a.phel'), $meta);
        $warner->maybeWarn('phel.core', $this->locatedSymbol('set-meta!', '/app/b.phel'), $meta);

        self::assertCount(2, $this->capturedDeprecations());
    }

    public function test_suppresses_uses_inside_phels_own_stdlib(): void
    {
        $this->warner()->maybeWarn(
            'phel.core',
            $this->locatedSymbol('set-meta!', dirname(__DIR__, 6) . '/src/phel/walk.phel'),
            $this->meta(['deprecated' => '0.32.0']),
        );

        self::assertSame([], $this->capturedDeprecations());
    }

    public function test_stays_silent_when_the_call_site_has_no_location(): void
    {
        $this->warner()->maybeWarn(
            'phel.core',
            Symbol::create('set-meta!'),
            $this->meta(['deprecated' => '0.32.0']),
        );

        self::assertSame([], $this->capturedDeprecations());
    }

    public function test_suppresses_expansion_of_a_bundled_stdlib_macro(): void
    {
        // A phel.core macro whose expansion calls a deprecated definition: the
        // call is stamped with the user's call site, but it was written in
        // phel's own stdlib, so the user must not be told to fix their file.
        $this->warner()->maybeWarn(
            'phel.core',
            $this->expandedSymbol('set-meta!', '/app/user.phel', dirname(__DIR__, 6) . '/src/phel/core/meta.phel'),
            $this->meta(['deprecated' => '0.32.0']),
        );

        self::assertSame([], $this->capturedDeprecations());
    }

    public function test_attributes_expansion_of_a_user_macro_to_the_macro_file(): void
    {
        $this->warner()->maybeWarn(
            'my.app',
            $this->expandedSymbol('old-fn', '/app/user.phel', '/app/macros.phel'),
            $this->meta(['deprecated' => true]),
        );

        $captured = $this->capturedDeprecations();
        self::assertCount(1, $captured);
        self::assertStringContainsString("'my.app/old-fn' used at /app/macros.phel:7", $captured[0]);
        self::assertStringContainsString('reached by expanding a macro at /app/user.phel:3', $captured[0]);
    }

    public function test_suppresses_expansion_whose_origin_is_unknown(): void
    {
        // No `:start-location` on the macro definition: the call site is still
        // the wrong place to report, so stay silent rather than misattribute.
        $symbol = Symbol::create('old-fn');
        $symbol->setStartLocation(new SourceLocation('/app/user.phel', 3, 1, SourceLocation::unknown()));

        $this->warner()->maybeWarn('my.app', $symbol, $this->meta(['deprecated' => true]));

        self::assertSame([], $this->capturedDeprecations());
    }

    public function test_still_warns_for_a_deprecated_call_the_user_wrote_inside_a_macro_call(): void
    {
        // A macro argument keeps its own reader location, so it is never
        // marked as expansion output.
        $this->warner()->maybeWarn(
            'my.app',
            $this->locatedSymbol('old-fn', '/app/user.phel'),
            $this->meta(['deprecated' => true]),
        );

        $captured = $this->capturedDeprecations();
        self::assertCount(1, $captured);
        self::assertStringContainsString("'my.app/old-fn' used at /app/user.phel:1", $captured[0]);
    }

    private function warner(): DeprecatedDefinitionWarner
    {
        return DeprecatedDefinitionWarner::getInstance();
    }

    private function locatedSymbol(string $name, string $file): Symbol
    {
        $symbol = Symbol::create($name);
        $symbol->setStartLocation(new SourceLocation($file, 1, 1));

        return $symbol;
    }

    /**
     * A symbol as macro expansion leaves it: located at the call site, but
     * carrying the definition it was really written in.
     */
    private function expandedSymbol(string $name, string $callSiteFile, string $originFile): Symbol
    {
        $symbol = Symbol::create($name);
        $symbol->setStartLocation(new SourceLocation(
            $callSiteFile,
            3,
            1,
            new SourceLocation($originFile, 7, 2),
        ));

        return $symbol;
    }

    /**
     * @param array<string, mixed> $pairs
     *
     * @return PersistentMapInterface<mixed, mixed>
     */
    private function meta(array $pairs): PersistentMapInterface
    {
        $flat = [];
        foreach ($pairs as $key => $value) {
            $flat[] = Keyword::create($key);
            $flat[] = $value;
        }

        return Phel::map(...$flat);
    }
}
