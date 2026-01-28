<?php

declare(strict_types=1);

namespace Phel\Build\Domain\ValueObject;

use Phel;
use Phel\Shared\BuildConstants;
use Phel\Shared\CompilerConstants;

/**
 * Value Object managing build mode context.
 * Replaces static BuildFacade::enableBuildMode()/disableBuildMode() calls.
 */
final class BuildContext
{
    private bool $isBuildMode = false;

    public function enableBuildMode(): void
    {
        $this->isBuildMode = true;
        Phel::addDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, BuildConstants::BUILD_MODE, true);
    }

    public function disableBuildMode(): void
    {
        $this->isBuildMode = false;
        Phel::addDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, BuildConstants::BUILD_MODE, false);
    }

    public function isBuildMode(): bool
    {
        return $this->isBuildMode;
    }

    /**
     * Executes a callback within build mode context.
     * Ensures build mode is properly disabled even if an exception occurs.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function executeInBuildMode(callable $callback): mixed
    {
        $this->enableBuildMode();

        try {
            return $callback();
        } finally {
            $this->disableBuildMode();
        }
    }
}
