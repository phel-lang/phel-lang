<?php

declare(strict_types=1);

namespace PhelTest\Integration\Api;

use Phel;
use Phel\Api\ApiConfig;
use Phel\Api\ApiFacade;
use Phel\Api\Transfer\PhelFunction;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_unique;
use function array_values;
use function count;
use function implode;
use function sprintf;

final class ApiFacadeTest extends TestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_can_load_phel_functions_from_all_namespaces(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $facade = new ApiFacade();
        $functions = $facade->getPhelFunctions(ApiConfig::allNamespaces());

        self::assertGreaterThan(350, count($functions));
        $this->assertAllNamespacesAreLoaded($functions);
        $this->assertAllFunctionsHaveValidStructure($functions);
    }

    /**
     * @param list<PhelFunction> $functions
     */
    private function assertAllNamespacesAreLoaded(array $functions): void
    {
        $loadedNamespaces = array_values(array_unique(array_map(
            static fn(PhelFunction $fn): string => $fn->namespace,
            $functions,
        )));
        $expectedNamespaces = array_map(
            static fn(string $ns): string => str_replace('phel\\', '', $ns),
            ApiConfig::allNamespaces(),
        );

        foreach ($expectedNamespaces as $expectedNamespace) {
            self::assertContains(
                $expectedNamespace,
                $loadedNamespaces,
                sprintf(
                    "Expected namespace '%s' to be loaded. Loaded namespaces: %s",
                    $expectedNamespace,
                    implode(', ', $loadedNamespaces),
                ),
            );
        }

        self::assertNotNull(
            $this->findFunction($functions, 'async', 'delay'),
            'Expected phel.async/delay to be loaded as the public phel.async function.',
        );
    }

    /**
     * @param list<PhelFunction> $functions
     */
    private function assertAllFunctionsHaveValidStructure(array $functions): void
    {
        foreach ($functions as $fn) {
            self::assertInstanceOf(PhelFunction::class, $fn);
            self::assertNotEmpty($fn->namespace);
            self::assertNotEmpty($fn->name);
        }
    }

    /**
     * @param list<PhelFunction> $functions
     */
    private function findFunction(array $functions, string $namespace, string $name): ?PhelFunction
    {
        foreach ($functions as $fn) {
            if ($fn->namespace === $namespace && $fn->name === $name) {
                return $fn;
            }
        }

        return null;
    }
}
