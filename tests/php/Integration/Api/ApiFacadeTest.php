<?php

declare(strict_types=1);

namespace PhelTest\Integration\Api;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Api\ApiConfig;
use Phel\Api\ApiFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class ApiFacadeTest extends TestCase
{
    public function test_number_of_grouped_functions(): never
    {// This test in isolation works, but when running with all other integration tests fails,
        // because the IntegrationTest loads already the core and all internal code, which
        // it crashes when it tries to load all phel funcs again. See: `PhelFnLoader::loadAllPhelFunctions()`
        $this->markTestSkipped('Useful to debug the facade, but useless to keep it in the CI');

        Gacela::bootstrap(__DIR__, GacelaConfig::defaultPhpConfig());

        Registry::getInstance()->clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $facade = new ApiFacade();
        $groupedFns = $facade->getPhelFunctions(
            ApiConfig::allNamespaces(),
        );

        self::assertCount(272, $groupedFns);
    }
}
