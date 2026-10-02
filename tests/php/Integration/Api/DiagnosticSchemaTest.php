<?php

declare(strict_types=1);

namespace PhelTest\Integration\Api;

use Phel;
use Phel\Api\Infrastructure\Command\AnalyzeCommand;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Lint\Infrastructure\Command\LintCommand;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Exceptions\ErrorCodeCatalog;
use Phel\Shared\LintRuleCodes;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function json_decode;

/**
 * One error, two tools: `phel analyze` and `phel lint --format=json` must
 * describe it with the same `errorCode`, `suggestions` and `fix` (#3464).
 */
final class DiagnosticSchemaTest extends TestCase
{
    private const string FIXTURE_DIR = __DIR__ . '/Fixtures/schema';

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_analyze_and_lint_describe_one_error_the_same_way(): void
    {
        $this->bootstrap();

        $analyze = new CommandTester(new AnalyzeCommand());
        $analyzeExit = $analyze->execute(['paths' => [self::FIXTURE_DIR . '/typo.phel']]);
        $analyzed = $this->only(json_decode(trim($analyze->getDisplay()), true));

        $lint = new CommandTester(new LintCommand());
        $lint->execute([
            'paths' => [self::FIXTURE_DIR . '/typo.phel'],
            '--format' => 'json',
            '--no-cache' => true,
        ]);
        $linted = $this->only(json_decode(trim($lint->getDisplay()), true));

        self::assertSame(1, $analyzeExit, 'an error diagnostic fails the run');
        self::assertSame('PHEL001', $analyzed['code']);
        self::assertSame(LintRuleCodes::UNRESOLVED_SYMBOL, $linted['code']);

        self::assertSame('PHEL001', $analyzed['errorCode']);
        self::assertContains('println', $analyzed['suggestions']);
        self::assertSame(ErrorCodeCatalog::explain(ErrorCode::UNDEFINED_SYMBOL)->fix, $analyzed['fix']);

        foreach (['errorCode', 'suggestions', 'fix'] as $field) {
            self::assertSame($analyzed[$field], $linted[$field], $field);
        }
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_analyze_walks_a_directory(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new AnalyzeCommand());
        $exit = $tester->execute(['paths' => [self::FIXTURE_DIR]]);

        self::assertSame(1, $exit, $tester->getDisplay());
        $diagnostic = $this->only(json_decode(trim($tester->getDisplay()), true));
        self::assertStringEndsWith('typo.phel', $diagnostic['uri']);
    }

    /**
     * @return array<string, mixed>
     */
    private function only(mixed $payload): array
    {
        self::assertIsArray($payload);
        self::assertCount(1, $payload);
        self::assertIsArray($payload[0]);

        return $payload[0];
    }

    private function bootstrap(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();
    }
}
