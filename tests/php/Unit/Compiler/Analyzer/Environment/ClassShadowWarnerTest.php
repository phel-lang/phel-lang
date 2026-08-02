<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\Environment;

use Phel\Compiler\Domain\Analyzer\Environment\ClassShadowWarner;
use Phel\Compiler\Domain\Diagnostic\CompilerWarnings;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use PhelTest\Support\CapturesCompilerWarningsTrait;
use PHPUnit\Framework\TestCase;

final class ClassShadowWarnerTest extends TestCase
{
    use CapturesCompilerWarningsTrait;

    protected function setUp(): void
    {
        CompilerWarnings::reset();
        $this->startCapturingCompilerWarnings();
    }

    protected function tearDown(): void
    {
        $this->stopCapturingCompilerWarnings();
        CompilerWarnings::reset();
    }

    public function test_warns_when_a_def_shadows_a_php_class(): void
    {
        ClassShadowWarner::getInstance()->maybeWarn('user', $this->named('DateTime'));

        $captured = $this->capturedCompilerWarnings();
        self::assertCount(1, $captured);
        self::assertStringContainsString('DateTime is mapped to the PHP class DateTime', $captured[0]);
        self::assertStringContainsString('\\DateTime', $captured[0], 'names the spelling that still reaches the class');
    }

    public function test_is_silent_for_an_ordinary_phel_name(): void
    {
        ClassShadowWarner::getInstance()->maybeWarn('user', $this->named('my-thing'));

        self::assertSame([], $this->capturedCompilerWarnings());
    }

    public function test_is_silent_for_a_name_php_cannot_spell(): void
    {
        // The cheap filter that keeps `def` off the autoloader: these can never
        // be a class, so the existence probe never runs.
        foreach (['empty?', 'set!', 'a->b', '*ns*'] as $name) {
            ClassShadowWarner::getInstance()->maybeWarn('user', $this->named($name));
        }

        self::assertSame([], $this->capturedCompilerWarnings());
    }

    public function test_is_silent_for_a_pascal_case_name_that_is_no_class(): void
    {
        ClassShadowWarner::getInstance()->maybeWarn('user', $this->named('NotARealClassAnywhere'));

        self::assertSame([], $this->capturedCompilerWarnings());
    }

    public function test_dedupes_per_file_and_name(): void
    {
        ClassShadowWarner::getInstance()->maybeWarn('user', $this->named('DateTime'));
        ClassShadowWarner::getInstance()->maybeWarn('user', $this->named('DateTime'));

        self::assertCount(1, $this->capturedCompilerWarnings());
    }

    public function test_is_silent_without_a_location(): void
    {
        ClassShadowWarner::getInstance()->maybeWarn('user', Symbol::create('DateTime'));

        self::assertSame([], $this->capturedCompilerWarnings());
    }

    private function named(string $name): Symbol
    {
        $symbol = Symbol::create($name);
        $symbol->setStartLocation(new SourceLocation('/app/user.phel', 1, 1));

        return $symbol;
    }
}
