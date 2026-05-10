<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class StaticTypeCheckerTest extends TestCase
{
    private CompilerFacade $compilerFacade;

    public static function setUpBeforeClass(): void
    {
        Phel::bootstrap(__DIR__);
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();
        new BuildFacade()->compileFile(
            __DIR__ . '/../../../../../../src/phel/core.phel',
            tempnam(sys_get_temp_dir(), 'phel-core'),
        );
    }

    protected function setUp(): void
    {
        GlobalEnvironmentSingleton::getInstance()->setNs('user');
        Symbol::resetGen();
        $this->compilerFacade = new CompilerFacade();
    }

    public function test_call_site_arg_type_mismatch_raises_diagnostic(): void
    {
        $this->expectExceptionMessageMatches(
            "/Arg #1 to 'add' has type 'string' but param is tagged 'int'/",
        );
        $this->compile('(defn ^int add [^int x ^int y] (php/+ x y))(add "x" 1)');
    }

    public function test_call_site_arg_type_match_compiles_without_error(): void
    {
        $php = $this->compile('(defn ^int add [^int x ^int y] (php/+ x y))(add 1 2)');
        self::assertStringContainsString('add', $php);
    }

    public function test_untagged_param_is_unchecked(): void
    {
        // Body returns its first arg verbatim — no `+`-on-string runtime
        // surprise; the assertion is that the analyzer accepts the call
        // without throwing despite the type-shape mismatch a tagged
        // version would have caught.
        $php = $this->compile('(defn pick-first [x y] x)(pick-first "x" 1)');
        self::assertStringContainsString('pick-first', $php);
    }

    public function test_recur_arg_type_mismatch_raises_diagnostic(): void
    {
        $this->expectExceptionMessageMatches(
            "/'recur arg #1 has type 'string' but param '\w+' is tagged 'int'/",
        );
        $this->compile('(loop [^int i 0] (recur "x"))');
    }

    public function test_return_type_literal_mismatch_raises_diagnostic(): void
    {
        $this->expectExceptionMessageMatches(
            "/Fn return type 'int' is incompatible with tail expression of type 'string'/",
        );
        $this->compile('(fn ^int [] "wrong")');
    }

    public function test_return_type_compatible_literal_compiles(): void
    {
        $php = $this->compile('(fn ^int [] 42)');
        self::assertStringContainsString('return 42', $php);
    }

    public function test_nullable_return_accepts_nil_branch(): void
    {
        $php = $this->compile('(fn ^"?int" [^int x] (if (php/< x 0) nil x))');
        self::assertStringContainsString('?int', $php);
    }

    public function test_inferred_int_param_flags_string_literal_at_call_site(): void
    {
        $this->expectExceptionMessageMatches(
            "/Arg #1 to 'add1' has type 'string' but param is tagged 'int'/",
        );
        $this->compile('(defn add1 [x] (php/+ x 1))(add1 "x")');
    }

    public function test_inferred_string_param_flags_int_literal_at_call_site(): void
    {
        $this->expectExceptionMessageMatches(
            "/Arg #1 to 'shout' has type 'int' but param is tagged 'string'/",
        );
        $this->compile('(defn shout [x] (php/. x "!"))(shout 1)');
    }

    public function test_inferred_param_does_not_emit_php_signature_hint(): void
    {
        // Advisory inference must not change the runtime contract: the
        // emitted PHP keeps the param untyped so callers that rely on
        // PHP's int/float/string coercion still work.
        $php = $this->compile('(defn add1 [x] (php/+ x 1))');
        self::assertStringNotContainsString('int $x', $php);
        self::assertStringContainsString('__invoke($x)', $php);
    }

    public function test_branch_disagreement_drops_inferred_param(): void
    {
        // `x` is used as both int and string across branches, so the
        // inferrer must not commit to either; both call shapes compile
        // without a diagnostic.
        $php = $this->compile(
            '(defn mixed [x flag] (if flag (php/+ x 1) (php/. x "!")))'
            . '(mixed 1 true)'
            . '(mixed "x" false)',
        );
        self::assertStringContainsString('mixed', $php);
    }

    public function test_explicit_param_tag_wins_over_inferred(): void
    {
        // The body suggests `string` for `x` via `php/.`, but the
        // explicit `^int` declaration is the authoritative contract;
        // the diagnostic must use the explicit tag.
        $this->expectExceptionMessageMatches(
            "/Arg #1 to 'tag-wins' has type 'string' but param is tagged 'int'/",
        );
        $this->compile('(defn tag-wins [^int x] (php/. x "!"))(tag-wins "x")');
    }

    private function compile(string $source): string
    {
        BuildFacade::enableBuildMode();
        try {
            return $this->compilerFacade
                ->compile($source, new CompileOptions())
                ->getPhpCode();
        } finally {
            BuildFacade::disableBuildMode();
        }
    }
}
