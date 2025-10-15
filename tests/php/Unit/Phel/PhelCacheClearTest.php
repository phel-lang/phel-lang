<?php

declare(strict_types=1);

namespace PhelTest\Unit\Phel;

use Phel\Phel;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;

final class PhelCacheClearTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('phel_cache_', true);

        mkdir($this->projectRoot . DIRECTORY_SEPARATOR . '.phel-cache', 0777, true);
        file_put_contents($this->projectRoot . DIRECTORY_SEPARATOR . '.phel-cache/cache-index.json', '{}');
    }

    protected function tearDown(): void
    {
        Phel::cacheClear($this->projectRoot);
        if (is_dir($this->projectRoot)) {
            rmdir($this->projectRoot);
        }
    }

    public function test_cache_clear_removes_cache_directory(): void
    {
        Phel::cacheClear($this->projectRoot);

        self::assertDirectoryDoesNotExist($this->projectRoot . DIRECTORY_SEPARATOR . '.phel-cache');
    }
}
