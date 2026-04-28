<?php

declare(strict_types=1);

namespace PhelTest\Integration\Api;

use Phel;
use Phel\Api\ApiFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ApiFacadeReplCompletePreservesEnvTest extends TestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_repl_complete_preserves_current_namespace(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        GlobalEnvironmentSingleton::getInstance()->setNs('user');
        Phel::addDefinition('user', 'x', 1);

        new ApiFacade()->replComplete('m');

        self::assertSame(
            'user',
            GlobalEnvironmentSingleton::getInstance()->getNs(),
            'replComplete must not switch the global namespace away from the active REPL namespace.',
        );
        self::assertSame(
            1,
            Phel::getDefinition('user', 'x'),
            'replComplete must not wipe user-defined symbols.',
        );
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_get_phel_functions_preserves_current_namespace(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        GlobalEnvironmentSingleton::getInstance()->setNs('user');
        Phel::addDefinition('user', 'x', 1);

        new ApiFacade()->getPhelFunctions(['phel\\core']);

        self::assertSame('user', GlobalEnvironmentSingleton::getInstance()->getNs());
        self::assertSame(1, Phel::getDefinition('user', 'x'));
    }
}
