<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\Environment;

use Phel\Compiler\Domain\Analyzer\Environment\BackslashSeparatorDeprecator;
use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;
use Phel\Lang\Delay;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use PhelTest\Support\CapturesDeprecationsTrait;
use PHPUnit\Framework\TestCase;

use function dirname;

final class BackslashSeparatorDeprecatorTest extends TestCase
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

    public function test_emits_for_backslash_namespace_symbol(): void
    {
        $this->deprecator()->maybeWarn($this->locatedRawNameSymbol('phel\\core/map', '/app/user.phel'));

        $captured = $this->capturedDeprecations();
        self::assertCount(1, $captured);
        self::assertStringContainsString("'phel\\core/map'", $captured[0]);
        self::assertStringContainsString("'phel.core/map'", $captured[0]);
        self::assertStringContainsString('/app/user.phel', $captured[0]);
    }

    public function test_emits_for_leading_backslash_class_fqn(): void
    {
        $this->deprecator()->maybeWarn($this->locatedSymbol(null, '\\Phel\\Lang\\Foo', '/app/user.phel'));

        $captured = $this->capturedDeprecations();
        self::assertCount(1, $captured);
        self::assertStringContainsString("'\\Phel\\Lang\\Foo'", $captured[0]);
        self::assertStringContainsString("'Phel.Lang.Foo'", $captured[0]);
    }

    public function test_announces_even_when_warnings_are_disabled(): void
    {
        // The one deprecation that does not wait for `--warn-deprecations`:
        // it is scheduled for removal at the next major, and a notice nobody
        // is shown does not keep the policy's promise of a minor of warning.
        DeprecationWarnings::disable();

        $this->deprecator()->maybeWarn($this->locatedRawNameSymbol('phel\\core/map', '/app/user.phel'));

        self::assertCount(1, $this->capturedDeprecations());
    }

    public function test_stays_silent_for_a_dependency(): void
    {
        DeprecationWarnings::disable();

        $this->deprecator()->maybeWarn(
            $this->locatedRawNameSymbol('phel\\core/map', '/app/vendor/acme/lib/src/x.phel'),
        );

        self::assertSame([], $this->capturedDeprecations(), 'a dependency is not the user to fix it');
    }

    public function test_no_warning_for_dot_separated_symbol(): void
    {
        $this->deprecator()->maybeWarn($this->locatedSymbol('phel.core', 'map', '/app/user.phel'));

        self::assertSame([], $this->capturedDeprecations());
    }

    public function test_dedupes_same_file_and_pattern(): void
    {
        $deprecator = $this->deprecator();
        $deprecator->maybeWarn($this->locatedRawNameSymbol('phel\\core/map', '/app/user.phel'));
        $deprecator->maybeWarn($this->locatedRawNameSymbol('phel\\core/map', '/app/user.phel'));

        self::assertCount(1, $this->capturedDeprecations());
    }

    public function test_emits_again_for_different_file(): void
    {
        $deprecator = $this->deprecator();
        $deprecator->maybeWarn($this->locatedRawNameSymbol('phel\\core/map', '/app/a.phel'));
        $deprecator->maybeWarn($this->locatedRawNameSymbol('phel\\core/map', '/app/b.phel'));

        self::assertCount(2, $this->capturedDeprecations());
    }

    public function test_suppresses_warnings_from_phel_stdlib_sources(): void
    {
        $this->deprecator()->maybeWarn($this->locatedRawNameSymbol(
            'phel\\core/map',
            dirname(__DIR__, 6) . '/src/phel/walk.phel',
        ));

        self::assertSame([], $this->capturedDeprecations());
    }

    public function test_warns_for_user_nested_layout_sources(): void
    {
        $this->deprecator()->maybeWarn($this->locatedRawNameSymbol('my\\project/run', '/app/src/phel/main.phel'));

        self::assertCount(1, $this->capturedDeprecations());
    }

    public function test_emits_for_backslash_namespace_string(): void
    {
        $this->deprecator()->maybeWarnString('my\\project', new SourceLocation('/app/user.phel', 1, 1));

        $captured = $this->capturedDeprecations();
        self::assertCount(1, $captured);
        self::assertStringContainsString("'my\\project'", $captured[0]);
        self::assertStringContainsString("'my.project'", $captured[0]);
    }

    public function test_no_warning_for_leading_only_backslash_php_global_constant(): void
    {
        $this->deprecator()->maybeWarn($this->locatedSymbol(null, '\\JSON_UNESCAPED_SLASHES', '/app/user.phel'));

        self::assertSame([], $this->capturedDeprecations());
    }

    public function test_no_warning_for_leading_only_backslash_php_global_function(): void
    {
        $this->deprecator()->maybeWarn($this->locatedSymbol(null, '\\strlen', '/app/user.phel'));

        self::assertSame([], $this->capturedDeprecations());
    }

    public function test_no_warning_for_leading_only_backslash_top_level_class(): void
    {
        $this->deprecator()->maybeWarn($this->locatedSymbol(null, '\\DateTimeInterface', '/app/user.phel'));

        self::assertSame([], $this->capturedDeprecations());
    }

    public function test_emits_for_leading_backslash_when_internal_separator_present(): void
    {
        $this->deprecator()->maybeWarn($this->locatedSymbol(null, '\\Foo\\Bar', '/app/user.phel'));

        $captured = $this->capturedDeprecations();
        self::assertCount(1, $captured);
        self::assertStringContainsString("'\\Foo\\Bar'", $captured[0]);
        self::assertStringContainsString("'Foo.Bar'", $captured[0]);
    }

    public function test_suppresses_when_location_is_missing(): void
    {
        $this->deprecator()->maybeWarn(Symbol::createForNamespace('phel.core', 'map'));

        self::assertSame([], $this->capturedDeprecations());
    }

    public function test_suppresses_expansion_of_a_bundled_stdlib_macro(): void
    {
        // `(delay ...)` expands to `(php/new \Phel\Lang\Delay ...)`. The symbol
        // is stamped with the user's call site, but the `\` was written in
        // phel's own stdlib, so the user must not be told to fix their file.
        $this->deprecator()->maybeWarn($this->expandedSymbol(
            '\\' . Delay::class,
            '/app/user.phel',
            dirname(__DIR__, 6) . '/src/phel/core/lazy.phel',
        ));

        self::assertSame([], $this->capturedDeprecations());
    }

    public function test_attributes_expansion_of_a_user_macro_to_the_macro_file(): void
    {
        $this->deprecator()->maybeWarn($this->expandedSymbol(
            '\\App\\Thing',
            '/app/user.phel',
            '/app/macros.phel',
        ));

        $captured = $this->capturedDeprecations();
        self::assertCount(1, $captured);
        self::assertStringContainsString('/app/macros.phel:7', $captured[0]);
        self::assertStringContainsString('reached by expanding a macro at /app/user.phel:3', $captured[0]);
    }

    public function test_suppresses_expansion_whose_origin_is_unknown(): void
    {
        // No `:start-location` on the macro definition: the call site is still
        // the wrong place to report, so stay silent rather than misattribute.
        $symbol = Symbol::createForNamespace(null, '\\App\\Thing');
        $symbol->setStartLocation(
            new SourceLocation('/app/user.phel', 3, 1, SourceLocation::unknown()),
        );

        $this->deprecator()->maybeWarn($symbol);

        self::assertSame([], $this->capturedDeprecations());
    }

    public function test_still_warns_for_a_backslash_the_user_wrote_inside_a_macro_call(): void
    {
        // `(delay (php/new \App\Thing))`: the argument keeps its own reader
        // location, so it is never marked as expansion output.
        $this->deprecator()->maybeWarn($this->locatedSymbol(null, '\\App\\Thing', '/app/user.phel'));

        self::assertCount(1, $this->capturedDeprecations());
    }

    private function deprecator(): BackslashSeparatorDeprecator
    {
        return BackslashSeparatorDeprecator::getInstance();
    }

    private function locatedSymbol(?string $ns, string $name, string $file): Symbol
    {
        $symbol = $ns === null ? Symbol::create($name) : Symbol::createForNamespace($ns, $name);
        $symbol->setStartLocation(new SourceLocation($file, 1, 1));

        return $symbol;
    }

    /**
     * Builds a Symbol whose raw name preserves backslash separators verbatim
     * so the deprecator sees the legacy form via `getFullName()` without the
     * canonical dot translation kicking in.
     */
    private function locatedRawNameSymbol(string $rawName, string $file): Symbol
    {
        $symbol = Symbol::createForNamespace(null, $rawName);
        $symbol->setStartLocation(new SourceLocation($file, 1, 1));

        return $symbol;
    }

    /**
     * A symbol as macro expansion leaves it: located at the call site, but
     * carrying the definition it was really written in.
     */
    private function expandedSymbol(string $rawName, string $callSiteFile, string $originFile): Symbol
    {
        $symbol = Symbol::createForNamespace(null, $rawName);
        $symbol->setStartLocation(new SourceLocation(
            $callSiteFile,
            3,
            1,
            new SourceLocation($originFile, 7, 2),
        ));

        return $symbol;
    }
}
