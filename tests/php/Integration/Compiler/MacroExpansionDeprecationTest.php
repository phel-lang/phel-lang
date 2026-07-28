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
 * `(new \Phel\Lang\Delay ...)`) look like backslash separators the *user*
 * had written, reporting a deprecation against a file whose author could not
 * act on it (#2827). A macro whose expansion *calls a `:deprecated`
 * definition* misattributes the same way.
 *
 * Three directions have to keep working independently: an expansion of a
 * bundled stdlib macro stays silent, an expansion of a macro the user wrote is
 * reported against the file that defines it, and code the user typed at the
 * call site is still reported there.
 */
final class MacroExpansionDeprecationTest extends TestCase
{
    /**
     * A path inside phel's own `src/phel`, which is what
     * `DeprecationWarnings::isBundledStdlibSource()` keys the stdlib
     * suppression on. Using a real bundled file keeps the test honest about
     * what a `phel.core` macro's `:start-location` looks like.
     */
    private const string STDLIB_MACRO_SOURCE = __DIR__ . '/../../../../src/phel/core/lazy.phel';

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
        $warnings = $this->compileCapturingDeprecations('(new \Phel\Lang\Delay (fn [] 42))');

        self::assertCount(1, $warnings);
        self::assertStringContainsString('\\' . Delay::class, $warnings[0]);
        self::assertStringContainsString('Phel.Lang.Delay', $warnings[0]);
    }

    public function test_a_user_backslash_fqn_passed_to_a_bundled_macro_is_still_reported(): void
    {
        // The argument keeps the reader's own location, so it must survive the
        // expansion-origin suppression that silences the macro's own body.
        $warnings = $this->compileCapturingDeprecations('(delay (new \Phel\Lang\Delay (fn [] 1)))');

        self::assertCount(1, $warnings);
        self::assertStringContainsString('\\' . Delay::class, $warnings[0]);
    }

    public function test_bundled_macro_expanding_to_a_deprecated_definition_does_not_blame_the_call_site(): void
    {
        $this->defineDeprecatedMacroIn('depstdlib', self::STDLIB_MACRO_SOURCE);

        self::assertSame(
            [],
            $this->compileCapturingDeprecations('(use-old)'),
            'A `:deprecated` call inside a phel.core macro must not be reported against the caller.',
        );
    }

    public function test_user_macro_expanding_to_a_deprecated_definition_blames_the_macro_file(): void
    {
        $this->defineDeprecatedMacroIn('depmacro', '/app/macros.phel');

        $warnings = $this->compileCapturingDeprecations('(use-old)');

        self::assertCount(1, $warnings);
        self::assertStringContainsString("'depmacro/old-fn' used at /app/macros.phel:", $warnings[0]);
        self::assertStringContainsString('reached by expanding a macro at /app/user.phel:1', $warnings[0]);
    }

    public function test_a_direct_call_to_a_deprecated_definition_is_still_reported_at_the_call_site(): void
    {
        $this->defineDeprecatedMacroIn('depdirect', '/app/macros.phel');

        $warnings = $this->compileCapturingDeprecations('(old-fn)');

        self::assertCount(1, $warnings);
        self::assertStringContainsString("'depdirect/old-fn' used at /app/user.phel:1", $warnings[0]);
        self::assertStringNotContainsString('reached by expanding', $warnings[0]);
    }

    /**
     * Defines a deprecated `old-fn` plus a `use-old` macro whose expansion
     * calls it, in namespace `$ns`, as if both had been written in
     * `$macroSource`. The macro body is a quasiquote, so the symbol it splices
     * in is rebuilt at expansion time with no location of its own — exactly the
     * shape that makes the analyzer stamp the caller's position onto it.
     *
     * Each case needs its own namespace: the global environment is shared
     * across the class, and re-`def`ing a bound symbol is an error.
     *
     * The dedup table is cleared afterwards so the notice the reader already
     * raised while analysing the macro body does not mask the one under test.
     */
    private function defineDeprecatedMacroIn(string $ns, string $macroSource): void
    {
        self::$globalEnv->setNs($ns);

        $options = new CompileOptions()->setSource($macroSource);
        $this->compilerFacade->eval(
            '(def old-fn {:deprecated "1.4.0" :superseded-by "new-fn"} (fn [] 42))',
            $options,
        );
        $this->compilerFacade->eval('(defmacro use-old [] `(old-fn))', $options);

        DeprecationWarnings::reset();
        DeprecationWarnings::enable();
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
