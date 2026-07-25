<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Delay;
use Phel\Lang\Symbol;
use Phel\Shared\CompileOptions;
use PHPUnit\Framework\TestCase;

use function restore_error_handler;
use function set_error_handler;

use const E_USER_DEPRECATED;

/**
 * A macro pastes its body into the caller's file, and the analyzer stamps the
 * call site onto every form of the expansion so errors stay locatable. That
 * made `phel.core`'s own `\`-form class FQNs (`(delay ...)` expands to
 * `(php/new \Phel\Lang\Delay ...)`) look like backslash separators the *user*
 * had written, reporting a deprecation against a file whose author could not
 * act on it (#2827).
 *
 * The two directions have to keep working independently: stdlib expansions
 * stay silent, and a `\` the user typed is still reported.
 */
final class MacroExpansionDeprecationTest extends TestCase
{
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
        DeprecationWarnings::reset();
        DeprecationWarnings::enable();
    }

    protected function tearDown(): void
    {
        DeprecationWarnings::reset();
    }

    public function test_bundled_macro_expansion_does_not_blame_the_call_site(): void
    {
        self::assertSame(
            [],
            $this->compileCapturingDeprecations('(delay 42)'),
            'A `\`-form FQN inside a phel.core macro must not be reported against the caller.',
        );
    }

    public function test_a_backslash_fqn_the_user_wrote_is_still_reported(): void
    {
        $warnings = $this->compileCapturingDeprecations('(php/new \Phel\Lang\Delay (fn [] 42))');

        self::assertCount(1, $warnings);
        self::assertStringContainsString('\\' . Delay::class, $warnings[0]);
        self::assertStringContainsString('Phel.Lang.Delay', $warnings[0]);
    }

    public function test_a_user_backslash_fqn_passed_to_a_bundled_macro_is_still_reported(): void
    {
        // The argument keeps the reader's own location, so it must survive the
        // expansion-origin suppression that silences the macro's own body.
        $warnings = $this->compileCapturingDeprecations('(delay (php/new \Phel\Lang\Delay (fn [] 1)))');

        self::assertCount(1, $warnings);
        self::assertStringContainsString('\\' . Delay::class, $warnings[0]);
    }

    /**
     * @return list<string>
     */
    private function compileCapturingDeprecations(string $phelCode): array
    {
        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;
            return true;
        }, E_USER_DEPRECATED);

        try {
            $this->compilerFacade
                ->compile($phelCode, new CompileOptions()->setSource('/app/user.phel'))
                ->getPhpCode();
        } finally {
            restore_error_handler();
        }

        return $warnings;
    }
}
