<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use PhelTest\Support\CapturesCompilerWarningsTrait;
use PHPUnit\Framework\TestCase;

final class BareHostClassPositionWarningTest extends TestCase
{
    use CapturesCompilerWarningsTrait;

    private const string LOADABLE_ALL_CAPS_CLASS = 'PHEL_TEST_CLASS_POSITION_HOST';

    private AnalyzerInterface $analyzer;

    public static function setUpBeforeClass(): void
    {
        if (!class_exists(self::LOADABLE_ALL_CAPS_CLASS)) {
            eval('final class ' . self::LOADABLE_ALL_CAPS_CLASS . ' { const NAME = 1; public static function make() { return new self(); } }');
        }
    }

    protected function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
        $this->startCapturingCompilerWarnings();
    }

    protected function tearDown(): void
    {
        $this->stopCapturingCompilerWarnings();
    }

    public function test_constructor_target_does_not_warn(): void
    {
        $this->analyze([Symbol::create(Symbol::NAME_PHP_NEW), $this->host()]);

        self::assertSame([], $this->capturedCompilerWarnings());
    }

    public function test_static_constant_access_target_does_not_warn(): void
    {
        $this->analyze([Symbol::create('.-NAME'), $this->host()]);

        self::assertSame([], $this->capturedCompilerWarnings());
    }

    public function test_static_call_target_does_not_warn(): void
    {
        $this->analyze([
            Symbol::create(Symbol::NAME_PHP_OBJECT_STATIC_CALL),
            $this->host(),
            Phel::list([Symbol::create('make')]),
        ]);

        self::assertSame([], $this->capturedCompilerWarnings());
    }

    public function test_callable_target_does_not_warn(): void
    {
        $this->analyze([Symbol::create(Symbol::NAME_PHP_CALLABLE), $this->host(), Symbol::create('make')]);

        self::assertSame([], $this->capturedCompilerWarnings());
    }

    public function test_value_position_still_warns(): void
    {
        $this->analyzer->analyze($this->host(), NodeEnvironment::empty());

        $warnings = $this->capturedCompilerWarnings();
        self::assertCount(1, $warnings);
        self::assertStringContainsString('reads as the global constant', $warnings[0]);
    }

    /**
     * @param list<mixed> $forms
     */
    private function analyze(array $forms): void
    {
        $this->analyzer->analyze(Phel::list($forms), NodeEnvironment::empty());
    }

    private function host(): Symbol
    {
        $symbol = Symbol::create(self::LOADABLE_ALL_CAPS_CLASS);
        $symbol->setStartLocation(new SourceLocation('/app/user.phel', 3, 5));

        return $symbol;
    }
}
