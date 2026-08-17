<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use Gacela\Console\Testing\ModuleAssertions;
use Phel\Phel;
use PHPUnit\Framework\TestCase;

use function dirname;

/**
 * `module-rules.json` is read by PHPStan (`DeclaredModuleDependencyRule`) and
 * Psalm (`<moduleRules>`), which run over `src/` in the quality gate. This is
 * the same file judged by the same graph builder inside the unit suite, so a
 * boundary written there holds in `composer test-compiler` too, or in neither.
 */
final class ModuleRulesTest extends TestCase
{
    use ModuleAssertions;

    protected function setUp(): void
    {
        Phel::bootstrap(dirname(__DIR__, 4));
    }

    public function test_the_declared_module_rules_hold(): void
    {
        self::assertModuleRulesHold(dirname(__DIR__, 4) . '/module-rules.json');
    }
}
