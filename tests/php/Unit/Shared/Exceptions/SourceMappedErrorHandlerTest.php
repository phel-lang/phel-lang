<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\Exceptions;

use Phel\Shared\Exceptions\SourceMappedErrorHandler;
use PHPUnit\Framework\TestCase;

use function error_reporting;
use function ini_get;
use function ini_set;

use const E_ALL;
use const E_ERROR;
use const E_USER_ERROR;
use const E_WARNING;
use const PHP_EOL;

final class SourceMappedErrorHandlerTest extends TestCase
{
    private string|false $displayErrors;

    private string|false $logErrors;

    private int $errorReporting;

    protected function setUp(): void
    {
        $this->displayErrors = ini_get('display_errors');
        $this->logErrors = ini_get('log_errors');
        $this->errorReporting = error_reporting();
        ini_set('display_errors', '1');
        ini_set('log_errors', '0');
        error_reporting(E_ALL);
    }

    protected function tearDown(): void
    {
        ini_set('display_errors', (string) $this->displayErrors);
        ini_set('log_errors', (string) $this->logErrors);
        error_reporting($this->errorReporting);
    }

    public function test_reports_a_mapped_warning_at_the_phel_source(): void
    {
        $this->expectOutputString(PHP_EOL . 'Warning: careful in /proj/src/main.phel on line 4' . PHP_EOL);

        self::assertTrue($this->handler()(E_WARNING, 'careful', '/tmp/__phel_abc.php', 13));
    }

    public function test_hands_an_unmapped_file_back_to_php(): void
    {
        $this->expectOutputString('');

        self::assertFalse($this->handler()(E_WARNING, 'careful', '/proj/src/helper.php', 7));
    }

    public function test_hands_a_silenced_warning_back_to_php(): void
    {
        error_reporting(E_ERROR);
        $this->expectOutputString('');

        self::assertFalse($this->handler()(E_WARNING, 'careful', '/tmp/__phel_abc.php', 13));
    }

    public function test_hands_an_error_level_it_does_not_report_back_to_php(): void
    {
        $this->expectOutputString('');

        self::assertFalse($this->handler()(E_USER_ERROR, 'boom', '/tmp/__phel_abc.php', 13));
    }

    private function handler(): SourceMappedErrorHandler
    {
        return new SourceMappedErrorHandler(
            static fn(string $file, int $line): ?array => $file === '/tmp/__phel_abc.php' && $line === 13
                ? ['/proj/src/main.phel', 4]
                : null,
        );
    }
}
