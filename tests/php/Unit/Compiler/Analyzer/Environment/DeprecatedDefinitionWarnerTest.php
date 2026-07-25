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
