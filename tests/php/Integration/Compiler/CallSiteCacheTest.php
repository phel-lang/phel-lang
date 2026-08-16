<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use Phel\Shared\CompileOptions;
use PHPUnit\Framework\TestCase;

final class CallSiteCacheTest extends TestCase
{
    private CompilerFacade $compiler;

    protected function setUp(): void
    {
        Phel::bootstrap(__DIR__);
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        // The compiler routes call-site caching off the runtime registry
        // entry `phel.core/*build-mode*`, so the production bootstrap must
        // have loaded core before either mode is exercised.
        new BuildFacade()->compileFile(
            __DIR__ . '/../../../../src/phel/core.phel',
            tempnam(sys_get_temp_dir(), 'phel-core'),
        );

        $this->compiler = new CompilerFacade();
    }

    public function test_build_mode_on_hoists_call_sites_to_static_slots(): void
    {
        // Use a vector so both `+` call sites are live (the simplification
        // pass drops pure non-tail expressions, which would otherwise
        // remove the first call before the cache could see it).
        BuildFacade::enableBuildMode();
        $output = $this->compileSnippet('(fn [x] [(+ x 1) (+ x 2)])');
        BuildFacade::disableBuildMode();

        $phel = '\\' . Phel::class;

        self::assertStringContainsString('static $__phel_call_0', $output);
        self::assertStringContainsString('($__phel_call_0 ??= ' . $phel . '::getDefinition("phel.core", "+"))->__invoke($x, 1)', $output);
        self::assertStringContainsString('($__phel_call_1 ??= ' . $phel . '::getDefinition("phel.core", "+"))->__invoke($x, 2)', $output);
    }

    public function test_build_mode_off_skips_cache_so_repl_redefine_still_wins(): void
    {
        BuildFacade::disableBuildMode();
        $output = $this->compileSnippet('(fn [x] [(+ x 1) (+ x 2)])');

        $registry = '\\' . Registry::class;

        self::assertStringNotContainsString('$__phel_call_', $output);
        // Uncached, so each call reads the definition afresh and a REPL
        // redefine wins. `+` carries no `^:dynamic`/`^:redef`, so the read is
        // the registry root (#3179); what matters here is that it is a read
        // per call rather than a pinned slot.
        self::assertStringContainsString('(' . $registry . '::readRoot("phel.core", "+"))->__invoke($x, 1)', $output);
        self::assertStringContainsString('(' . $registry . '::readRoot("phel.core", "+"))->__invoke($x, 2)', $output);
    }

    public function test_call_method_dispatch_targets_only_known_fn_defs(): void
    {
        BuildFacade::enableBuildMode();
        $output = $this->compileSnippet('(def k :a) (k {:a 1})');
        BuildFacade::disableBuildMode();

        $registry = '\\' . Registry::class;

        // `k` resolves to a Keyword (callable via __invoke), but the
        // analyzer has no `arglists` meta for it, so we keep the legacy
        // magic-dispatch form rather than risk `->__invoke` on a non-AbstractFn.
        self::assertStringContainsString('(' . $registry . '::readRoot("user", "k"))(', $output);
        self::assertStringNotContainsString('(' . $registry . '::readRoot("user", "k"))->__invoke', $output);
    }

    private function compileSnippet(string $phel): string
    {
        GlobalEnvironmentSingleton::getInstance()->setNs('user');
        Symbol::resetGen();

        $options = new CompileOptions()
            ->setSource(self::class);

        return $this->compiler->compile($phel, $options)->getPhpCode();
    }
}
