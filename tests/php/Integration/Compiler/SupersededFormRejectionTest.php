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
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\Exceptions\ErrorCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `php/new`, `php/->` and `php/::` are rejected as source from 1.0.0, but they
 * are also what the Clojure-style shorthand *compiles to*: the analyzer
 * rewrites `(.m obj)` into `(php/-> obj (m))` before dispatch. A check placed
 * after that rewrite would reject every shorthand in the language, so the
 * shorthand still compiling is the property worth pinning (#2877).
 *
 * `set-var` has the same shape one level up: `binding` and `with-redefs`
 * expand into it, so the two macros must keep compiling too (#2888).
 */
final class SupersededFormRejectionTest extends TestCase
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
    public function test_the_clojure_shorthand_still_compiles(string $phelCode, string $shorthand): void
    {
        $this->compile($phelCode);

        self::assertTrue(true, $shorthand . ' must keep compiling to the php/* form it expands to.');
    }

    #[DataProvider('provideSupersededForm')]
    public function test_a_superseded_form_written_directly_is_rejected(string $phelCode, string $form): void
    {
        try {
            $this->compile($phelCode);
        } catch (CompilerException $compilerException) {
            self::assertStringContainsString($form, $compilerException->getNestedException()->getMessage());
            self::assertSame(ErrorCode::SUPERSEDED_FORM, $compilerException->getNestedException()->getErrorCode());
            return;
        }

        self::fail($form . ' was accepted as source');
    }

    public function test_set_var_written_directly_is_rejected(): void
    {
        $this->compilerFacade->eval('(def ^:dynamic *probe* 1)', new CompileOptions()->setSource('/app/user.phel'));

        $this->expectException(CompilerException::class);
        $this->expectExceptionMessage('alter-var-root');

        $this->compile('(set-var *probe* 2)');
    }

    /**
     * `binding` opens a frame and then emits one `set-var` per pair, so the
     * whole macro is built out of the deprecated form. The notice belongs to
     * `src/phel/core/io.phel`, which the stdlib suppression drops.
     */
    public function test_binding_and_with_redefs_still_compile(): void
    {
        $this->compilerFacade->eval('(def ^:dynamic *frame* 1)', new CompileOptions()->setSource('/app/user.phel'));

        $this->compile('(binding [*frame* 2] *frame*)');
        $this->compile('(with-redefs [*frame* 3] *frame*)');

        $this->expectNotToPerformAssertions();
    }

    /**
     * `definterface` generates one wrapper fn per method, each dispatching to
     * the PHP object. Those wrappers are written in `protocols.phel`, so a
     * user's `definterface` must not inherit a notice from them.
     */
    public function test_definterface_still_compiles(): void
    {
        $this->compile('(definterface Greeter (greet [this name]))');

        $this->expectNotToPerformAssertions();
    }

    private function compile(string $phelCode): void
    {
        $this->compilerFacade
            ->compile($phelCode, new CompileOptions()->setSource('/app/user.phel'))
            ->getPhpCode();
    }
}
