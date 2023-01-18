<?php

declare(strict_types=1);

namespace PhelTest\Integration\Api;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Api\ApiFacade;
use PHPUnit\Framework\TestCase;

final class ApiFacadeTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        Gacela::bootstrap(__DIR__, GacelaConfig::withPhpConfigDefault());
    }

    public function test_number_of_grouped_functions(): void
    {
        // This test in isolation works, but when running with all other integration tests fails,
        // because the IntegrationTest loads already the core and all internal code, which
        // it crashes when it tries to load all phel funcs again. See: `PhelFnLoader::loadAllPhelFunctions()`
        $this->markTestSkipped('Useful to debug the facade, but useless to keep it in the CI');
        $facade = new ApiFacade();
        $groupedFns = $facade->getNormalizedGroupedFunctions();

        self::assertCount(212, $groupedFns);
    }
}
