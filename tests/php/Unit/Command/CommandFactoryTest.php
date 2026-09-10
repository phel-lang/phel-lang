<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Command\CommandFactory;
use Phel\Config\PhelConfig;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_get_contents;
use function getenv;
use function putenv;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

final class CommandFactoryTest extends TestCase
{
    use RemoveDirTrait;

    private string $tmpDir;

    private string $errorLogFile;

    private ?string $previousNoColor = null;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phel-command-factory-' . uniqid();
        $this->errorLogFile = $this->tmpDir . DIRECTORY_SEPARATOR . 'error.log';

        // The log must stay plain even when the terminal asks for colour.
        $noColor = getenv('NO_COLOR');
        $this->previousNoColor = $noColor === false ? null : $noColor;
        putenv('NO_COLOR');

        Gacela::bootstrap(__DIR__, function (GacelaConfig $config): void {
            $config->addAppConfigKeyValue(PhelConfig::ERROR_LOG_FILE, $this->errorLogFile);
        });
    }

    protected function tearDown(): void
    {
        if ($this->previousNoColor === null) {
            putenv('NO_COLOR');
        } else {
            putenv('NO_COLOR=' . $this->previousNoColor);
        }

        $this->removeDir($this->tmpDir);
    }

    public function test_the_error_log_stays_plain_text_when_the_terminal_is_coloured(): void
    {
        new CommandFactory()->createCommandExceptionWriter()->logStackTrace(new RuntimeException('boom'));

        $contents = (string) file_get_contents($this->errorLogFile);
        self::assertStringNotContainsString("\033", $contents);
        self::assertStringContainsString('RuntimeException: boom', $contents);
        self::assertMatchesRegularExpression(
            '~^\[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}\] \S~',
            $contents,
        );
    }
}
