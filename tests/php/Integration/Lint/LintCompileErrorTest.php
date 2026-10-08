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

use function array_map;
use function json_decode;
use function trim;

/**
 * A file `phel run` rejects must not lint clean (#3621).
 */
final class LintCompileErrorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{string, ?string, int, int}>}>
     */
    public static function provideFiles(): iterable
    {
        yield 'duplicate case constant' => ['CompileError/duplicate_case.phel', [['phel/compile-error', 'PHEL005', 3, 13]]];
        yield 'def outside the namespace' => ['CompileError/qualified_def.phel', [['phel/compile-error', 'PHEL007', 3, 6]]];
        yield ':keys entry that is not a symbol' => ['CompileError/keys_not_symbol.phel', [['phel/compile-error', 'PHEL008', 3, 10]]];
        yield 'error a dedicated rule reports inside the span' => ['CompileError/odd_let.phel', [['phel/invalid-destructuring', null, 3, 6]]];
        yield 'superseded form passed through' => ['superseded_form.phel', [
            ['PHEL012', 'PHEL012', 5, 1],
            ['PHEL012', 'PHEL012', 6, 1],
            ['PHEL012', 'PHEL012', 7, 1],
            ['PHEL012', 'PHEL012', 8, 1],
        ]];
        yield 'syntax error passed through' => ['unparsable.phel', [['PHEL100', 'PHEL100', 4, 1]]];
    }

    /**
     * @param list<array{string, ?string, int, int}> $expected
     */
    #[DataProvider('provideFiles')]
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_compile_error_is_reported_once(string $fixture, array $expected): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $tester = new CommandTester(new LintCommand());
        $exit = $tester->execute([
            'paths' => [__DIR__ . '/Fixtures/' . $fixture],
            '--format' => 'json',
            '--no-cache' => true,
        ]);

        self::assertSame(1, $exit, $tester->getDisplay());
        $payload = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($payload, $tester->getDisplay());
        self::assertSame(
            $expected,
            array_map(
                static fn(array $d): array => [$d['code'], $d['errorCode'], $d['startLine'], $d['startCol']],
                $payload,
            ),
        );
    }
}
