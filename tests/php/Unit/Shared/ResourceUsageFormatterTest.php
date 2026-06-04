<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Shared\ResourceUsageFormatter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ResourceUsageFormatterTest extends TestCase
{
    private ResourceUsageFormatter $formatter;

    /** @var float|null */
    private mixed $originalRequestTimeFloat;

    private bool $requestTimeFloatWasSet = false;

    protected function setUp(): void
    {
        $this->formatter = new ResourceUsageFormatter();
        $this->requestTimeFloatWasSet = isset($_SERVER['REQUEST_TIME_FLOAT']);
        $this->originalRequestTimeFloat = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->requestTimeFloatWasSet) {
            $_SERVER['REQUEST_TIME_FLOAT'] = $this->originalRequestTimeFloat;
        } else {
            unset($_SERVER['REQUEST_TIME_FLOAT']);
        }
    }

    public function test_throws_when_request_time_float_missing(): void
    {
        unset($_SERVER['REQUEST_TIME_FLOAT']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('REQUEST_TIME_FLOAT');

        $this->formatter->resourceUsageSinceStartOfRequest();
    }

    public function test_returns_time_and_memory_snapshot(): void
    {
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);

        $result = $this->formatter->resourceUsageSinceStartOfRequest();

        // Format: "Time: MM:SS[.mmm], Memory: X.XX <unit>" (hours optional).
        self::assertMatchesRegularExpression(
            '/^Time: (?:\d{2}:)?\d{2}:\d{2}(?:\.\d{3})?, Memory: (?:\d+(?:\.\d{2} (?:GB|MB|KB)| bytes?))$/',
            $result,
        );
    }
}
