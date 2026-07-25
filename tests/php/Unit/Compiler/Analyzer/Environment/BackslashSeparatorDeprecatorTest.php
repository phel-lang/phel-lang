<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\Environment;

use Phel\Compiler\Domain\Analyzer\Environment\BackslashSeparatorDeprecator;
use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;
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

    public function test_stays_silent_when_warnings_are_disabled(): void
    {
        DeprecationWarnings::disable();

        $this->deprecator()->maybeWarn($this->locatedRawNameSymbol('phel\\core/map', '/app/user.phel'));

        self::assertSame([], $this->capturedDeprecations());
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
}
