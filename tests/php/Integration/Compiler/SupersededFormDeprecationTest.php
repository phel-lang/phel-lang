<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Iterator;
use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Shared\CompileOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function restore_error_handler;
use function set_error_handler;

use const E_USER_DEPRECATED;

/**
 * `php/new`, `php/->` and `php/::` are deprecated as source, but they are also
 * what the Clojure-style shorthand *compiles to*: the analyzer rewrites
 * `(.m obj)` into `(php/-> obj (m))` before dispatch. A detector placed after
 * that rewrite would warn about every shorthand in the language, so the
 * shorthand staying silent is the property worth pinning (#2877).
 *
 * `set-var` has the same shape one level up: `binding` and `with-redefs`
 * expand into it, so the two macros must stay silent too (#2888).
 */
final class SupersededFormDeprecationTest extends TestCase
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

    /**
     * @return Iterator<int<0, max>, array{string, string}>
     */
    public static function provideShorthand(): Iterator
    {
        yield 'constructor' => ['(new \DateTime "2024-03-10")', 'new'];
        yield 'dot constructor' => ['(\DateTime. "2024-03-10")', '\C.'];
        yield 'method' => ['(let [d (new \DateTime)] (.format d "Y"))', '.m'];
        yield 'property' => ['(let [o (new \ArrayObject)] (.-x o))', '.-f'];
        yield 'static method' => ['(\DateTimeImmutable/createFromFormat "Y-m-d" "2024-03-10")', '\C/m'];
        yield 'class constant' => ['\DateTime/ATOM', '\C/CONST'];
        yield 'method as value' => ['(map \DateTime/.format [])', '\C/.m'];
    }

    /**
     * @return Iterator<int<0, max>, array{string, string}>
     */
    public static function provideSupersededForm(): Iterator
    {
        yield 'php/new' => ['(php/new \DateTime "2024-03-10")', '"php/new"'];
        yield 'php/->' => ['(let [d (new \DateTime)] (php/-> d (format "Y")))', '"php/->"'];
        yield 'php/::' => ['(php/:: \DateTime (createFromFormat "Y-m-d" "2024-03-10"))', '"php/::"'];
    }

    #[DataProvider('provideShorthand')]
    public function test_the_clojure_shorthand_does_not_warn_about_what_it_expands_to(
        string $phelCode,
        string $shorthand,
    ): void {
        self::assertSame(
            [],
            $this->compileCapturingDeprecations($phelCode),
            $shorthand . ' must not warn about the php/* form it expands to.',
        );
    }

    #[DataProvider('provideSupersededForm')]
    public function test_a_superseded_form_written_directly_warns(string $phelCode, string $form): void
    {
        $warnings = $this->compileCapturingDeprecations($phelCode);

        self::assertCount(1, $warnings);
        self::assertStringContainsString($form, $warnings[0]);
    }

    public function test_set_var_written_directly_warns(): void
    {
        $this->compilerFacade->eval('(def ^:dynamic *probe* 1)', new CompileOptions()->setSource('/app/user.phel'));

        $warnings = $this->compileCapturingDeprecations('(set-var *probe* 2)');

        self::assertCount(1, $warnings);
        self::assertStringContainsString('alter-var-root', $warnings[0]);
    }

    /**
     * `binding` opens a frame and then emits one `set-var` per pair, so the
     * whole macro is built out of the deprecated form. The notice belongs to
     * `src/phel/core/io.phel`, which the stdlib suppression drops.
     */
    public function test_binding_and_with_redefs_stay_silent(): void
    {
        $this->compilerFacade->eval('(def ^:dynamic *frame* 1)', new CompileOptions()->setSource('/app/user.phel'));

        self::assertSame([], $this->compileCapturingDeprecations('(binding [*frame* 2] *frame*)'));
        self::assertSame([], $this->compileCapturingDeprecations('(with-redefs [*frame* 3] *frame*)'));
    }

    /**
     * `definterface` generates one wrapper fn per method, each dispatching to
     * the PHP object. Those wrappers are written in `protocols.phel`, so a
     * user's `definterface` must not inherit a notice from them.
     */
    public function test_definterface_stays_silent(): void
    {
        self::assertSame(
            [],
            $this->compileCapturingDeprecations('(definterface Greeter (greet [this name]))'),
        );
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
