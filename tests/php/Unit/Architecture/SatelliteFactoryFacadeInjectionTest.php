<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use Generator;
use Phel\Balance\BalanceFactory;
use Phel\Formatter\FormatterFactory;
use Phel\Lint\LintFacade;
use Phel\Lint\LintFactory;
use Phel\Lsp\LspFactory;
use Phel\Nrepl\NreplFactory;
use Phel\Profile\ProfileFactory;
use Phel\Shared\Facade\ApiFacadeInterface;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Facade\FormatterFacadeInterface;
use Phel\Shared\Facade\RunFacadeInterface;
use Phel\Watch\WatchFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;

use function dirname;

/**
 * Satellite modules must depend on the Shared facade *contracts*, not on a
 * neighbour module's concrete facade. The factory getter return type is the
 * one place that pins this down, so we lock it here against regressions.
 */
final class SatelliteFactoryFacadeInjectionTest extends TestCase
{
    #[DataProvider('factoryGetterProvider')]
    public function test_factory_getter_returns_facade_interface(
        string $factory,
        string $method,
        string $expectedInterface,
    ): void {
        $returnType = new ReflectionMethod($factory, $method)->getReturnType();

        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame($expectedInterface, $returnType->getName());
    }

    public static function factoryGetterProvider(): Generator
    {
        yield 'Lsp run' => [LspFactory::class, 'getRunFacade', RunFacadeInterface::class];
        yield 'Lsp api' => [LspFactory::class, 'getApiFacade', ApiFacadeInterface::class];
        yield 'Lsp formatter' => [LspFactory::class, 'getFormatterFacade', FormatterFacadeInterface::class];

        yield 'Lint run' => [LintFactory::class, 'getRunFacade', RunFacadeInterface::class];
        yield 'Lint compiler' => [LintFactory::class, 'getCompilerFacade', CompilerFacadeInterface::class];
        yield 'Lint command' => [LintFactory::class, 'getCommandFacade', CommandFacadeInterface::class];
        yield 'Lint api' => [LintFactory::class, 'getApiFacade', ApiFacadeInterface::class];

        yield 'Watch run' => [WatchFactory::class, 'getRunFacade', RunFacadeInterface::class];
        yield 'Watch command' => [WatchFactory::class, 'getCommandFacade', CommandFacadeInterface::class];
        yield 'Watch build' => [WatchFactory::class, 'getBuildFacade', BuildFacadeInterface::class];
        yield 'Watch api' => [WatchFactory::class, 'getApiFacade', ApiFacadeInterface::class];

        yield 'Nrepl run' => [NreplFactory::class, 'getRunFacade', RunFacadeInterface::class];
        yield 'Nrepl api' => [NreplFactory::class, 'getApiFacade', ApiFacadeInterface::class];

        yield 'Profile run' => [ProfileFactory::class, 'getRunFacade', RunFacadeInterface::class];

        yield 'Formatter compiler' => [FormatterFactory::class, 'getCompilerFacade', CompilerFacadeInterface::class];
        yield 'Formatter command' => [FormatterFactory::class, 'getCommandFacade', CommandFacadeInterface::class];

        yield 'Balance compiler' => [BalanceFactory::class, 'getCompilerFacade', CompilerFacadeInterface::class];
        yield 'Balance command' => [BalanceFactory::class, 'getCommandFacade', CommandFacadeInterface::class];
    }

    /**
     * The data provider above only sees getters somebody remembered to list, so
     * it cannot notice a *new* factory binding a concrete facade. This walks
     * every factory instead and fails on the difference.
     *
     * Exactly one getter still returns a concrete facade: `Lsp` consumes
     * `LintFacade`, for which `src/php/CLAUDE.md` records that no interface
     * exists. Unlike the Api and Formatter contracts, widening that one is not
     * a signature change: `LintFacade` trades in `RuleSettings`, `LintCache`
     * and `LintResult`, so a Shared contract means relocating Lint's own
     * configuration and cache types first. It is listed rather than silently
     * allowed.
     */
    public function test_no_unlisted_factory_binds_a_concrete_facade(): void
    {
        $expected = [
            'Lsp/LspFactory.php::getLintFacade' => LintFacade::class,
        ];

        self::assertSame(
            $expected,
            $this->concreteFacadeGetters(),
            "A factory getter returns a concrete facade instead of a Shared *FacadeInterface.\n"
            . "src/php/CLAUDE.md requires injecting the interface. Add the method to the contract\n"
            . 'in Shared/Facade and widen the return type; only list it here if that is not yet possible.',
        );
    }

    public function test_run_facade_interface_declares_auto_detect_entry_point(): void
    {
        self::assertTrue(
            method_exists(RunFacadeInterface::class, 'autoDetectEntryPoint'),
            'Profile consumes RunFacade::autoDetectEntryPoint via the Shared contract.',
        );
    }

    /**
     * @return array<string, string> `<relative factory>::<getter>` => concrete facade FQN
     */
    private function concreteFacadeGetters(): array
    {
        $srcDir = dirname(__DIR__, 4) . '/src/php';
        $found = [];

        foreach (glob($srcDir . '/*/*Factory.php') ?: [] as $path) {
            $contents = (string) file_get_contents($path);
            $relative = str_replace($srcDir . '/', '', $path);

            preg_match_all(
                '/function\s+(\w+)\s*\(\s*\)\s*:\s*(\w*Facade)\b/',
                $contents,
                $matches,
                PREG_SET_ORDER,
            );

            foreach ($matches as [, $method, $shortName]) {
                if (preg_match('/^use\s+(Phel\\\\[\w\\\\]*\\\\' . $shortName . ');$/m', $contents, $import) !== 1) {
                    continue;
                }

                $found[$relative . '::' . $method] = $import[1];
            }
        }

        ksort($found);

        return $found;
    }
}
