<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\Environment;

use Phel\Compiler\Domain\Analyzer\Environment\ReferShadowWarner;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use PhelTest\Support\CapturesCompilerWarningsTrait;
use PHPUnit\Framework\TestCase;

use function dirname;

final class ReferShadowWarnerTest extends TestCase
{
    use CapturesCompilerWarningsTrait;

    protected function setUp(): void
    {
        $this->startCapturingCompilerWarnings();
    }

    protected function tearDown(): void
    {
        $this->stopCapturingCompilerWarnings();
    }

    public function test_warns_naming_both_vars(): void
    {
        $this->warner()->maybeWarn(
            'user',
            $this->locatedSymbol('doc', '/app/user.phel'),
            ['doc' => Symbol::create('phel.repl')],
        );

        $captured = $this->capturedCompilerWarnings();
        self::assertCount(1, $captured);
        self::assertStringContainsString("doc already refers to: #'phel.repl/doc", $captured[0]);
        self::assertStringContainsString('in namespace: user', $captured[0]);
        self::assertStringContainsString("being replaced by: #'user/doc", $captured[0]);
        self::assertStringContainsString('/app/user.phel:1', $captured[0]);
    }

    public function test_is_silent_when_the_name_is_not_referred(): void
    {
        $this->warner()->maybeWarn(
            'user',
            $this->locatedSymbol('doc', '/app/user.phel'),
            ['other' => Symbol::create('phel.repl')],
        );

        self::assertSame([], $this->capturedCompilerWarnings());
    }

    public function test_is_silent_for_a_name_symbol_without_a_location(): void
    {
        // A synthesised symbol names no file, and a warning has to point at an
        // edit the user can make.
        $this->warner()->maybeWarn(
            'user',
            Symbol::create('doc'),
            ['doc' => Symbol::create('phel.repl')],
        );

        self::assertSame([], $this->capturedCompilerWarnings());
    }

    public function test_is_silent_for_a_bundled_stdlib_source(): void
    {
        $this->warner()->maybeWarn(
            'phel.core',
            $this->locatedSymbol('get', dirname(__DIR__, 6) . '/src/phel/core/collections.phel'),
            ['get' => Symbol::create('phel.other')],
        );

        self::assertSame([], $this->capturedCompilerWarnings());
    }

    public function test_warns_once_per_file_and_symbol(): void
    {
        $refers = ['doc' => Symbol::create('phel.repl')];

        $this->warner()->maybeWarn('user', $this->locatedSymbol('doc', '/app/user.phel'), $refers);
        $this->warner()->maybeWarn('user', $this->locatedSymbol('doc', '/app/user.phel'), $refers);

        self::assertCount(1, $this->capturedCompilerWarnings());
    }

    private function warner(): ReferShadowWarner
    {
        return ReferShadowWarner::getInstance();
    }

    private function locatedSymbol(string $name, string $file): Symbol
    {
        $symbol = Symbol::create($name);
        $symbol->setStartLocation(new SourceLocation($file, 1, 1));

        return $symbol;
    }
}
