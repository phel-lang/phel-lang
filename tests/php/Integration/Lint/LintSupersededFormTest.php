<?php

declare(strict_types=1);

namespace PhelTest\Integration\Lint;

use Phel;
use Phel\Api\ApiFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Lint\Application\Config\RuleSettings;
use Phel\Lint\Application\FileCollector;
use Phel\Lint\Application\LintRunner;
use Phel\Lint\Application\RulePipeline;
use Phel\Lint\Application\SourceReader;
use Phel\Shared\Api\Diagnostic;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

use function array_map;
use function realpath;

/**
 * `phel run` and `phel eval` reject `php/new`, `php/->`, `php/::` and
 * `set-var` written in source, so a lint gate that passed them let through
 * code that cannot run (#3456).
 */
final class LintSupersededFormTest extends TestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_each_superseded_form_is_reported_as_an_error(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $runner = new LintRunner(
            new ApiFacade(),
            new FileCollector(),
            new SourceReader(new CompilerFacade()),
            new RulePipeline([]),
        );

        $path = realpath(__DIR__ . '/Fixtures/superseded_form.phel');
        self::assertIsString($path);

        $result = $runner->run([$path], new RuleSettings([]));

        self::assertSame(
            [['PHEL012', 5], ['PHEL012', 6], ['PHEL012', 7], ['PHEL012', 8]],
            array_map(static fn(Diagnostic $d): array => [$d->code, $d->startLine], $result->diagnostics),
        );
        self::assertSame(4, $result->errorCount());
    }
}
