<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\Environment;

use Phel\Compiler\Domain\Analyzer\Environment\AmbiguousBareHostWarner;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use PhelTest\Support\CapturesCompilerWarningsTrait;
use PHPUnit\Framework\TestCase;

use function dirname;

/**
 * The migration bridge for #3064: value position now reads a bare all-caps
 * name as the constant, and where a class of that name is loadable the reading
 * silently changed, which is what the always-on warning channel is for.
 */
final class AmbiguousBareHostWarnerTest extends TestCase
{
    use CapturesCompilerWarningsTrait;

    private const string LOADABLE_ALL_CAPS_CLASS = 'PHEL_TEST_AMBIGUOUS_BARE_HOST';

    public static function setUpBeforeClass(): void
    {
        if (!class_exists(self::LOADABLE_ALL_CAPS_CLASS)) {
            eval('final class ' . self::LOADABLE_ALL_CAPS_CLASS . ' {}');
        }
    }

    protected function setUp(): void
    {
        $this->startCapturingCompilerWarnings();
    }

    protected function tearDown(): void
    {
        $this->stopCapturingCompilerWarnings();
    }

    public function test_warns_when_a_class_shadows_the_constant_reading(): void
    {
        AmbiguousBareHostWarner::getInstance()->maybeWarn(
            $this->located(self::LOADABLE_ALL_CAPS_CLASS, '/app/user.phel'),
        );

        $warnings = $this->capturedCompilerWarnings();
        self::assertCount(1, $warnings);
        self::assertStringContainsString('reads as the global constant', $warnings[0]);
        self::assertStringContainsString('\\' . self::LOADABLE_ALL_CAPS_CLASS, $warnings[0]);
        self::assertStringContainsString('php/' . self::LOADABLE_ALL_CAPS_CLASS, $warnings[0]);
    }

    public function test_dedups_per_file_and_name(): void
    {
        $warner = AmbiguousBareHostWarner::getInstance();
        $warner->maybeWarn($this->located(self::LOADABLE_ALL_CAPS_CLASS, '/app/user.phel'));
        $warner->maybeWarn($this->located(self::LOADABLE_ALL_CAPS_CLASS, '/app/user.phel', line: 12));

        self::assertCount(1, $this->capturedCompilerWarnings());
    }

    public function test_stays_silent_for_a_plain_constant(): void
    {
        AmbiguousBareHostWarner::getInstance()->maybeWarn(
            $this->located('PHEL_TEST_NO_SUCH_CLASS_AT_ALL', '/app/user.phel'),
        );

        self::assertSame([], $this->capturedCompilerWarnings());
    }

    public function test_stays_silent_without_a_location(): void
    {
        AmbiguousBareHostWarner::getInstance()->maybeWarn(Symbol::create(self::LOADABLE_ALL_CAPS_CLASS));

        self::assertSame([], $this->capturedCompilerWarnings());
    }

    /**
     * A user cannot act on a name inside phel's own stdlib, so it must not
     * reach their output.
     */
    public function test_suppresses_warnings_from_phel_stdlib_sources(): void
    {
        AmbiguousBareHostWarner::getInstance()->maybeWarn(
            $this->located(
                self::LOADABLE_ALL_CAPS_CLASS,
                dirname(__DIR__, 6) . '/src/phel/core/protocols.phel',
            ),
        );

        self::assertSame([], $this->capturedCompilerWarnings());
    }

    private function located(string $name, string $file, int $line = 7): Symbol
    {
        $symbol = Symbol::create($name);
        $symbol->setStartLocation(new SourceLocation($file, $line, 3));

        return $symbol;
    }
}
