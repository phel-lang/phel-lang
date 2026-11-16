<?php

declare(strict_types=1);

namespace PhelTest\Integration\Lint;

use Phel;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Lint\Infrastructure\Command\LintCommand;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function json_decode;

final class LintClassReferenceTest extends TestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_imports_used_through_constructor_shorthand_and_type_tags_lint_clean(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $tester = new CommandTester(new LintCommand());
        $exit = $tester->execute([
            'paths' => [__DIR__ . '/Fixtures/ClassShorthand/class_shorthand.phel'],
            '--format' => 'json',
            '--no-cache' => true,
        ]);

        self::assertSame([], json_decode(trim($tester->getDisplay()), true));
        self::assertSame(0, $exit);
    }
}
