<?php

declare(strict_types=1);

namespace PhelTest\Unit\Console;

use Phel;
use Phel\Console\ConsoleFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Short aliases for high-frequency commands (#2507).
 */
final class CommandAliasesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provider(): iterable
    {
        yield 'run -> r' => ['run', 'r'];
        yield 'test -> t' => ['test', 't'];
        yield 'build -> b' => ['build', 'b'];
        yield 'eval -> e' => ['eval', 'e'];
        yield 'format -> fmt' => ['format', 'fmt'];
    }

    #[DataProvider('provider')]
    public function test_alias_resolves_to_command(string $name, string $alias): void
    {
        Phel::bootstrap(__DIR__);
        $application = new ConsoleFactory()->createConsoleBootstrap();

        // find() resolves both canonical names and aliases; an unknown or
        // ambiguous alias would throw.
        self::assertSame($name, $application->find($alias)->getName());
    }
}
