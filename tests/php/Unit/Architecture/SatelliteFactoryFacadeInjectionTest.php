<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use Generator;
use Phel\Api\ApiFacade;
use Phel\Formatter\FormatterFacade;
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

        yield 'Lint run' => [LintFactory::class, 'getRunFacade', RunFacadeInterface::class];
        yield 'Lint compiler' => [LintFactory::class, 'getCompilerFacade', CompilerFacadeInterface::class];
        yield 'Lint command' => [LintFactory::class, 'getCommandFacade', CommandFacadeInterface::class];

        yield 'Watch run' => [WatchFactory::class, 'getRunFacade', RunFacadeInterface::class];
        yield 'Watch command' => [WatchFactory::class, 'getCommandFacade', CommandFacadeInterface::class];
        yield 'Watch build' => [WatchFactory::class, 'getBuildFacade', BuildFacadeInterface::class];

        yield 'Nrepl run' => [NreplFactory::class, 'getRunFacade', RunFacadeInterface::class];
        yield 'Nrepl api' => [NreplFactory::class, 'getApiFacade', ApiFacadeInterface::class];

        yield 'Profile run' => [ProfileFactory::class, 'getRunFacade', RunFacadeInterface::class];

        yield 'Formatter compiler' => [FormatterFactory::class, 'getCompilerFacade', CompilerFacadeInterface::class];
        yield 'Formatter command' => [FormatterFactory::class, 'getCommandFacade', CommandFacadeInterface::class];
    }

    /**
     * The data provider above only sees getters somebody remembered to list, so
     * it cannot notice a *new* factory binding a concrete facade. This walks
     * every factory instead and fails on the difference.
     *
     * Three getters legitimately return a concrete facade: `Lint`, `Lsp` and
     * `Watch` consume Api methods (`analyzeSource`, `indexProject`, symbol
     * resolution) that `ApiFacadeInterface` does not declare, and `Lsp` consumes
     * `LintFacade`, for which `src/php/CLAUDE.md` records that no interface
     * exists. Narrowing those means widening the contracts first, so they are
     * listed rather than silently allowed.
     */
    public function test_no_unlisted_factory_binds_a_concrete_facade(): void
    {
        $expected = [
            'Lint/LintFactory.php::getApiFacade' => ApiFacade::class,
            'Lsp/LspFactory.php::getApiFacade' => ApiFacade::class,
            'Lsp/LspFactory.php::getFormatterFacade' => FormatterFacade::class,
            'Lsp/LspFactory.php::getLintFacade' => LintFacade::class,
            'Watch/WatchFactory.php::getApiFacade' => ApiFacade::class,
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
