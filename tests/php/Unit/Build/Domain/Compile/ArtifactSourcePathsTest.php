<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Domain\Compile;

use Phel\Build\Domain\Cache\SourceFilePath;
use Phel\Build\Domain\Compile\ArtifactSourcePath;
use Phel\Build\Domain\Compile\ArtifactSourcePaths;
use Phel\Shared\SourceMap\SourcePathMarker;
use PHPUnit\Framework\TestCase;

final class ArtifactSourcePathsTest extends TestCase
{
    public function test_relocation_preserves_identical_user_strings_and_maps_foreign_locations(): void
    {
        $paths = new ArtifactSourcePaths([
            '/build/src/main.phel' => '/deploy/out/app/main.php',
            '/build/vendor/library.phel' => '/deploy/out/vendor/library.php',
        ]);
        $code = <<<'PHP'
            <?php
            $user = "/build/src/main.phel";
            \Phel::location("/build/src/main.phel", 4, 2);
            \Phel::location("/build/vendor/library.phel", 7, 1);
            \Phel::addDefinition("phel.core", "*file*", "/build/src/main.phel");
            PHP;

        $rewritten = $paths->rewrite($code, '/deploy/out/app/main.php');

        self::assertStringContainsString('$user = "/build/src/main.phel";', $rewritten);
        self::assertSame(1, substr_count($rewritten, '/build/src/main.phel'));
        self::assertStringNotContainsString('/build/vendor/library.phel', $rewritten);
        self::assertStringContainsString("__DIR__ . '/main.phel'", $rewritten);
        self::assertStringContainsString("__DIR__ . '/../vendor/library.phel'", $rewritten);
        self::assertSame(substr_count($code, "\n"), substr_count($rewritten, "\n"));
        self::assertSame($rewritten, $paths->rewrite($rewritten, '/deploy/out/app/main.php'));
    }

    public function test_magic_path_markers_do_not_rewrite_ordinary_values(): void
    {
        $paths = new ArtifactSourcePaths(['/build/src/main.phel' => '/deploy/out/app/main.php']);
        $marker = SourcePathMarker::encode('/build/src/main.phel', true);
        $code = '<?php $user = "/build/src"; $magic = ' . $marker . '"/build/src";';

        $rewritten = $paths->rewrite($code, '/deploy/out/app/main.php');

        self::assertStringContainsString('$user = "/build/src";', $rewritten);
        self::assertSame(1, substr_count($rewritten, '/build/src'));
        self::assertStringContainsString('dirname(\\' . ArtifactSourcePath::class . '::resolve(', $rewritten);
        self::assertStringNotContainsString('phel-source-directory', $rewritten);
    }

    public function test_build_context_uses_original_paths_then_restores_deployed_paths(): void
    {
        $paths = new ArtifactSourcePaths(['/build/src/main.phel' => '/deploy/out/app/main.php']);
        $previous = ArtifactSourcePath::setOriginalSources($paths->originalSources());
        try {
            self::assertSame(SourceFilePath::normalize('/build/src/main.phel'), ArtifactSourcePath::resolve('/deploy/out/app/main.phel'));
        } finally {
            ArtifactSourcePath::setOriginalSources($previous);
        }

        self::assertStringEndsWith('/deploy/out/app/main.phel', str_replace('\\', '/', ArtifactSourcePath::resolve('/deploy/out/app/main.phel')));
    }

    public function test_nested_load_rewrite_is_idempotent_and_keeps_source_loads_during_builds(): void
    {
        $paths = new ArtifactSourcePaths(['/build/src/main.phel' => '/deploy/out/app/main.php']);
        $code = <<<'PHP'
            <?php
            $__phelCandidatePhel = $__phelSrcDir . '/' . $__phelAttempt . '.phel';
            $__phelCandidatePhel = $__phelCallerSrcDir . '/' . $__phelAttempt . '.phel';
            PHP;

        $rewritten = $paths->rewrite($code, '/deploy/out/app/main.php');

        self::assertStringContainsString("\$__phelCandidatePhel = \$__phelSrcDir . '/' . \$__phelAttempt . '.phel';", $rewritten);
        self::assertSame(1, substr_count($rewritten, '!$__phelBuildMode && is_file('));
        self::assertStringContainsString("? '.php' : '.phel'", $rewritten);
        self::assertSame($rewritten, $paths->rewrite($rewritten, '/deploy/out/app/main.php'));
    }
}
