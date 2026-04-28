<?php

declare(strict_types=1);

namespace Phel\Config;

final class ProjectLayoutDetector
{
    public function detectFromCurrentWorkingDirectory(): ProjectLayout
    {
        $cwd = getcwd();
        if ($cwd === false) {
            return ProjectLayout::Flat;
        }

        return $this->detect($cwd);
    }

    public function detect(string $projectRootDir): ProjectLayout
    {
        if (is_dir($projectRootDir . '/src/phel')) {
            return ProjectLayout::Nested;
        }

        if (is_dir($projectRootDir . '/src')) {
            return ProjectLayout::Flat;
        }

        return ProjectLayout::Root;
    }
}
