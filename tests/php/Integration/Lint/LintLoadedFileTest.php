<?php

declare(strict_types=1);

namespace PhelTest\Integration\Lint;

use Phel;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Lint\Infrastructure\Command\LintCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function json_decode;

final class LintLoadedFileTest extends TestCase
{
    #[DataProvider('providerLoadingPaths')]
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_resolves_a_symbol_defined_in_a_loaded_file(string $path): void
    {
        $this->assertLintsClean($path);
    }

    public static function providerLoadingPaths(): iterable
    {
        yield 'the loading file alone' => [__DIR__ . '/Fixtures/Load/app.phel'];
        yield 'the directory holding both files' => [__DIR__ . '/Fixtures/Load'];
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_lints_a_file_whose_load_target_is_missing(): void
    {
        $this->assertLintsClean(__DIR__ . '/Fixtures/missing_load.phel');
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_lints_a_file_that_loads_itself(): void
    {
        $this->assertLintsClean(__DIR__ . '/Fixtures/SelfLoad/self_load.phel');
    }

    private function assertLintsClean(string $path): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $tester = new CommandTester(new LintCommand());
        $exit = $tester->execute([
            'paths' => [$path],
            '--format' => 'json',
            '--no-cache' => true,
        ]);

        self::assertSame([], json_decode(trim($tester->getDisplay()), true));
        self::assertSame(0, $exit);
    }
}
