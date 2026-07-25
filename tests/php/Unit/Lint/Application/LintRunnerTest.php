<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application;

use Phel\Api\ApiFacade;
use Phel\Lint\Application\Config\RuleSettings;
use Phel\Lint\Application\FileCollector;
use Phel\Lint\Application\LintRunner;
use Phel\Lint\Application\RulePipeline;
use Phel\Lint\Application\SourceReader;
use Phel\Lint\Domain\Exception\LintSourceException;
use Phel\Shared\Facade\CompilerFacadeInterface;
use PHPUnit\Framework\TestCase;

use function chmod;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class LintRunnerTest extends TestCase
{
    private string $baseDir = '';

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/phel-lint-runner-' . uniqid();
        mkdir($this->baseDir, 0777, true);
    }

    protected function tearDown(): void
    {
        @chmod($this->baseDir . '/unreadable.phel', 0644);
        @unlink($this->baseDir . '/unreadable.phel');
        @rmdir($this->baseDir);
    }

    public function test_it_raises_instead_of_reporting_an_unreadable_file_as_clean(): void
    {
        $file = $this->baseDir . '/unreadable.phel';
        file_put_contents($file, "(ns unreadable)\n");
        chmod($file, 0000);

        if (@file_get_contents($file) !== false) {
            self::markTestSkipped('chmod has no effect (running as root?)');
        }

        $this->expectException(LintSourceException::class);
        // FileCollector realpath()s its input, so only the tail is asserted.
        $this->expectExceptionMessage('Cannot read file to lint: ');
        $this->expectExceptionMessageMatches('#unreadable\.phel$#');

        $this->runner()->run([$file], new RuleSettings([]));
    }

    private function runner(): LintRunner
    {
        return new LintRunner(
            new ApiFacade(),
            new FileCollector(),
            new SourceReader($this->createStub(CompilerFacadeInterface::class)),
            new RulePipeline([]),
        );
    }
}
