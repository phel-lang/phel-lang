<?php

declare(strict_types=1);

namespace PhelTest\Integration\Api;

use Phel;
use Phel\Api\Infrastructure\Command\DocCommand;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandCompletionTester;

final class DocCommandTest extends TestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_search_argument_completes_function_names(): void
    {
        $this->bootstrap();

        $tester = new CommandCompletionTester(new DocCommand());
        $suggestions = $tester->complete(['map']);

        self::assertContains('core/map', $suggestions);
        self::assertContains('core/map-indexed', $suggestions);
        self::assertNotContains('core/reduce', $suggestions, 'Suggestions must be filtered by the typed value');
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_ns_option_completes_namespaces(): void
    {
        $this->bootstrap();

        $tester = new CommandCompletionTester(new DocCommand());
        $suggestions = $tester->complete(['--ns', '']);

        self::assertContains('core', $suggestions);
        // Namespaces must be unique.
        self::assertSame(array_values(array_unique($suggestions)), $suggestions);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_format_option_completes_available_formats(): void
    {
        $this->bootstrap();

        $tester = new CommandCompletionTester(new DocCommand());
        $suggestions = $tester->complete(['--format', '']);

        self::assertSame(['table', 'json'], $suggestions);
    }

    private function bootstrap(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();
    }
}
