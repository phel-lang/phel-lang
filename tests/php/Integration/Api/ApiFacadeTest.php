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
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ApiFacadeTest extends TestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_number_of_grouped_functions(): void
    {
        Gacela::bootstrap(__DIR__, GacelaConfig::defaultPhpConfig());

        Registry::getInstance()->clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $facade = new ApiFacade();
        $groupedFns = $facade->getPhelFunctions(
            ApiConfig::allNamespaces(),
        );

        self::assertCount(321, $groupedFns);
    }
}
