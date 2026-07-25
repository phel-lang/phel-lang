<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Shared\CompileOptions;
use PHPUnit\Framework\TestCase;

use function ini_get;
use function restore_error_handler;

use function set_error_handler;

use const E_USER_DEPRECATED;

/**
 * `^:reference` is the historical spelling of `^:by-ref`. It stays accepted
 * until the next major, but it must announce itself under
 * `--warn-deprecations` so a project on the old spelling learns about it
 * before the removal lands.
 */
final class ByRefParamDeprecationTest extends TestCase
{
    /**
     * A deprecation notice must not name a concrete removal version: the
     * release it promises inevitably ships and the message goes stale (#2783).
     */
    private const string VERSION_REFERENCE = '/v?\d+\.\d+(\.\d+)?/';

    private static GlobalEnvironmentInterface $globalEnv;

    private CompilerFacade $compilerFacade;

    public static function setUpBeforeClass(): void
    {
        Phel::bootstrap(__DIR__);
        Symbol::resetGen();
        $globalEnv = GlobalEnvironmentSingleton::initializeNew();
        new BuildFacade()->compileFile(
            __DIR__ . '/../../../../src/phel/core.phel',
            tempnam(sys_get_temp_dir(), 'phel-core'),
        );
        self::$globalEnv = $globalEnv;
    }

    protected function setUp(): void
    {
        $this->compilerFacade = new CompilerFacade();
        self::$globalEnv->setNs('user');
        Symbol::resetGen();
        DeprecationWarnings::enable();
    }

    protected function tearDown(): void
    {
        DeprecationWarnings::reset();
    }

    public function test_legacy_reference_alias_is_silent_unless_warnings_are_enabled(): void
    {
        DeprecationWarnings::disable();

        self::assertNull(
            $this->compileCapturingDeprecation('(fn [^:reference parts] (php/array_push parts "x"))'),
        );
    }

    public function test_legacy_reference_alias_emits_deprecation(): void
    {
        $warning = $this->compileCapturingDeprecation('(fn [^:reference parts] (php/array_push parts "x"))');

        self::assertNotNull($warning);
        self::assertStringContainsString('"^:reference"', $warning);
        self::assertStringContainsString('"^:by-ref"', $warning);
        self::assertStringContainsString('a future release', $warning);
        self::assertDoesNotMatchRegularExpression(self::VERSION_REFERENCE, $warning);
    }

    public function test_canonical_by_ref_spelling_stays_silent(): void
    {
        self::assertNull(
            $this->compileCapturingDeprecation('(fn [^:by-ref parts] (php/array_push parts "x"))'),
        );
    }

    public function test_legacy_reference_alias_still_compiles_to_a_php_reference(): void
    {
        $warning = $this->compileCapturingDeprecation(
            '(fn [^:reference parts] (php/array_push parts "x"))',
            $compiled,
        );

        self::assertNotNull($warning);
        self::assertStringContainsString('&$parts', (string) $compiled);
    }

    /**
     * The `^:reference` detector is the only one that runs during *emission*,
     * and the emitter builds its output inside `ob_start()`. Under PHP CLI's
     * default `display_errors=1` (STDOUT) the notice text was written straight
     * into that buffer and spliced into the generated PHP, so
     * `--warn-deprecations` turned a working `^:reference` param into
     * `syntax error, unexpected token ":"` (#2827).
     *
     * The other tests here install a `set_error_handler` and so never exercise
     * PHP's own display; this one deliberately does not.
     */
    public function test_the_deprecation_notice_never_lands_in_the_emitted_php(): void
    {
        $previousDisplay = (string) ini_get('display_errors');
        $previousLog = (string) ini_get('log_errors');
        $previousReporting = error_reporting(E_ALL);
        ini_set('display_errors', 'STDOUT');
        ini_set('log_errors', '0');
        set_error_handler(null);

        try {
            $compiled = $this->compilerFacade
                ->compile('(fn [^:reference parts] (php/array_push parts "x"))', new CompileOptions()->setEmitOnly(true))
                ->getPhpCode();
        } finally {
            restore_error_handler();
            ini_set('log_errors', $previousLog);
            ini_set('display_errors', $previousDisplay);
            error_reporting($previousReporting);
        }

        self::assertStringContainsString('&$parts', $compiled);
        self::assertStringNotContainsString('Deprecated', $compiled);
        self::assertStringNotContainsString('^:reference', $compiled);
    }

    private function compileCapturingDeprecation(string $phelCode, ?string &$compiled = null): ?string
    {
        $warning = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$warning): bool {
            $warning = $errstr;
            return true;
        }, E_USER_DEPRECATED);

        try {
            $compiled = $this->compilerFacade
                ->compile($phelCode, new CompileOptions())
                ->getPhpCode();
        } finally {
            restore_error_handler();
        }

        return $warning;
    }
}
