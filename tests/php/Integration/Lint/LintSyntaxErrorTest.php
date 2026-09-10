<?php

declare(strict_types=1);

namespace PhelTest\Integration\Lint;

use Phel;
use Phel\Api\ApiFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Application\Config\RuleSettings;
use Phel\Lint\Application\FileCollector;
use Phel\Lint\Application\LintRunner;
use Phel\Lint\Application\Rule\CommentStyleRule;
use Phel\Lint\Application\RulePipeline;
use Phel\Lint\Application\SourceReader;
use Phel\Lint\Transfer\LintResult;
use Phel\Shared\Api\Diagnostic;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

use function array_map;
use function realpath;

/**
 * A file that does not lex or parse used to be reported as clean, with exit
 * code 0, because the best-effort reader swallowed the failure and every rule
 * then found nothing to complain about. A gate built on `phel lint` therefore
 * passed source that cannot compile (#3292).
 */
final class LintSyntaxErrorTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function brokenFixtureProvider(): iterable
    {
        yield 'does not lex' => ['unlexable.phel', 'PHEL310'];
        yield 'does not parse' => ['unparsable.phel', 'PHEL100'];
    }

    #[DataProvider('brokenFixtureProvider')]
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_file_that_does_not_read_is_reported_with_its_code(string $fixture, string $code): void
    {
        $result = $this->lint($fixture);

        self::assertContains($code, $this->codesOf($result), 'the syntax error is the one thing worth saying about the file');
        self::assertSame(1, $result->errorCount());
    }

    /**
     * The forms before the broken one still read, so the namespace is known
     * and the rules still see them. Best effort keeps working; only the
     * silence about the failure goes away.
     */
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_readable_file_is_still_reported_as_clean(): void
    {
        $result = $this->lint('clean.phel');

        self::assertSame([], $result->diagnostics);
    }

    /**
     * @return list<string>
     */
    private function codesOf(LintResult $result): array
    {
        return array_map(static fn(Diagnostic $d): string => $d->code, $result->diagnostics);
    }

    private function lint(string $fixture): LintResult
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $compilerFacade = new CompilerFacade();

        $runner = new LintRunner(
            new ApiFacade(),
            new FileCollector(),
            new SourceReader($compilerFacade),
            new RulePipeline([new CommentStyleRule($compilerFacade)]),
        );

        $path = realpath(__DIR__ . '/Fixtures/' . $fixture);
        self::assertIsString($path);

        return $runner->run([$path], new RuleSettings([
            RuleRegistry::COMMENT_STYLE => Diagnostic::SEVERITY_WARNING,
        ]));
    }
}
