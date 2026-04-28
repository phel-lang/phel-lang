<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\Environment;

use Phel\Compiler\Domain\Analyzer\Environment\BackslashSeparatorDeprecator;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

use function dirname;

final class BackslashSeparatorDeprecatorTest extends TestCase
{
    /** @var list<string> */
    private array $captured = [];

    protected function setUp(): void
    {
        $this->captured = [];
    }

    public function test_emits_for_backslash_namespace_symbol(): void
    {
        $deprecator = $this->deprecator(enabled: true);
        $deprecator->maybeWarn($this->locatedSymbol('phel\\core', 'map', '/app/user.phel'));

        self::assertCount(1, $this->captured);
        self::assertStringContainsString("'phel\\core/map'", $this->captured[0]);
        self::assertStringContainsString("'phel.core/map'", $this->captured[0]);
        self::assertStringContainsString('/app/user.phel', $this->captured[0]);
    }

    public function test_emits_for_leading_backslash_class_fqn(): void
    {
        $deprecator = $this->deprecator(enabled: true);
        $deprecator->maybeWarn($this->locatedSymbol(null, '\\Phel\\Lang\\Foo', '/app/user.phel'));

        self::assertCount(1, $this->captured);
        self::assertStringContainsString("'\\Phel\\Lang\\Foo'", $this->captured[0]);
        self::assertStringContainsString("'Phel.Lang.Foo'", $this->captured[0]);
    }

    public function test_stays_silent_when_disabled(): void
    {
        $deprecator = $this->deprecator(enabled: false);
        $deprecator->maybeWarn($this->locatedSymbol('phel\\core', 'map', '/app/user.phel'));

        self::assertSame([], $this->captured);
    }

    public function test_no_warning_for_dot_separated_symbol(): void
    {
        $deprecator = $this->deprecator(enabled: true);
        $deprecator->maybeWarn($this->locatedSymbol('phel.core', 'map', '/app/user.phel'));

        self::assertSame([], $this->captured);
    }

    public function test_dedupes_same_file_and_pattern(): void
    {
        $deprecator = $this->deprecator(enabled: true);
        $deprecator->maybeWarn($this->locatedSymbol('phel\\core', 'map', '/app/user.phel'));
        $deprecator->maybeWarn($this->locatedSymbol('phel\\core', 'map', '/app/user.phel'));

        self::assertCount(1, $this->captured);
    }

    public function test_emits_again_for_different_file(): void
    {
        $deprecator = $this->deprecator(enabled: true);
        $deprecator->maybeWarn($this->locatedSymbol('phel\\core', 'map', '/app/a.phel'));
        $deprecator->maybeWarn($this->locatedSymbol('phel\\core', 'map', '/app/b.phel'));

        self::assertCount(2, $this->captured);
    }

    public function test_suppresses_warnings_from_phel_stdlib_sources(): void
    {
        $deprecator = $this->deprecator(enabled: true);
        $deprecator->maybeWarn($this->locatedSymbol(
            'phel\\core',
            'map',
            dirname(__DIR__, 6) . '/src/phel/walk.phel',
        ));

        self::assertSame([], $this->captured);
    }

    public function test_warns_for_user_nested_layout_sources(): void
    {
        $deprecator = $this->deprecator(enabled: true);
        $deprecator->maybeWarn($this->locatedSymbol('my\\project', 'run', '/app/src/phel/main.phel'));

        self::assertCount(1, $this->captured);
    }

    public function test_emits_for_backslash_namespace_string(): void
    {
        $deprecator = $this->deprecator(enabled: true);
        $deprecator->maybeWarnString('my\\project', new SourceLocation('/app/user.phel', 1, 1));

        self::assertCount(1, $this->captured);
        self::assertStringContainsString("'my\\project'", $this->captured[0]);
        self::assertStringContainsString("'my.project'", $this->captured[0]);
    }

    public function test_suppresses_when_location_is_missing(): void
    {
        $deprecator = $this->deprecator(enabled: true);
        $deprecator->maybeWarn(Symbol::createForNamespace('phel\\core', 'map'));

        self::assertSame([], $this->captured);
    }

    private function deprecator(bool $enabled): BackslashSeparatorDeprecator
    {
        return new BackslashSeparatorDeprecator(
            $enabled,
            function (string $msg): void {
                $this->captured[] = $msg;
            },
        );
    }

    private function locatedSymbol(?string $ns, string $name, string $file): Symbol
    {
        $symbol = $ns === null ? Symbol::create($name) : Symbol::createForNamespace($ns, $name);
        $symbol->setStartLocation(new SourceLocation($file, 1, 1));

        return $symbol;
    }
}
