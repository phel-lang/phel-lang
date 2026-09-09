<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Domain\Exceptions;

use Phel\Command\Domain\Exceptions\InternalPathDetector;
use PHPUnit\Framework\TestCase;

final class InternalPathDetectorTest extends TestCase
{
    private const string PHEL_SRC_DIR = '/proj/vendor/phel-lang/phel-lang/src';

    private const string CACHE_DIR = '/proj/.phel/cache';

    public function test_core_library_source_is_internal(): void
    {
        self::assertTrue($this->detector()->isInternalSource(self::PHEL_SRC_DIR . '/phel/core/sequences.phel'));
    }

    public function test_project_source_is_not_internal(): void
    {
        self::assertFalse($this->detector()->isInternalSource('/proj/src/app/main.phel'));
    }

    public function test_a_sibling_directory_sharing_the_prefix_is_not_internal(): void
    {
        self::assertFalse($this->detector()->isInternalSource(self::PHEL_SRC_DIR . '-backup/phel/core/sequences.phel'));
    }

    public function test_compiled_cache_artifact_is_internal(): void
    {
        $artifact = self::CACHE_DIR . '/compiled/phel.core__e0b15747.php';

        self::assertTrue($this->detector()->isInternalArtifact($artifact));
        self::assertTrue($this->detector()->isInternalSource($artifact));
    }

    public function test_eval_temp_file_is_internal(): void
    {
        self::assertTrue($this->detector()->isInternalArtifact('/var/folders/T/phel/tmp/__phel_abc123.php'));
    }

    public function test_build_output_is_not_internal(): void
    {
        self::assertFalse($this->detector()->isInternalArtifact('/proj/out/phel/http.php'));
    }

    public function test_an_unconfigured_directory_matches_nothing(): void
    {
        $detector = new InternalPathDetector('', '');

        self::assertFalse($detector->isInternalSource('/proj/src/app/main.phel'));
    }

    private function detector(): InternalPathDetector
    {
        return new InternalPathDetector(self::PHEL_SRC_DIR, self::CACHE_DIR);
    }
}
