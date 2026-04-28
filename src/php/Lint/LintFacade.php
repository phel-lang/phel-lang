<?php

declare(strict_types=1);

namespace Phel\Lint;

use Gacela\Framework\AbstractFacade;
use Phel\Lint\Application\Cache\LintCache;
use Phel\Lint\Application\Config\RuleSettings;
use Phel\Lint\Application\Formatter\FormatterRegistry;
use Phel\Lint\Transfer\LintResult;

/**
 * @extends AbstractFacade<LintFactory>
 */
final class LintFacade extends AbstractFacade
{
    /**
     * @param list<string> $paths
     */
    public function lint(array $paths, RuleSettings $settings, ?LintCache $cache = null): LintResult
    {
        return $this->getFactory()
            ->createLintRunner($cache)
            ->run($paths, $settings);
    }

    public function loadSettings(string $configPath, RuleSettings $defaults): RuleSettings
    {
        return $this->getFactory()
            ->createConfigLoader()
            ->load($configPath, $defaults);
    }

    public function defaultSettings(): RuleSettings
    {
        return $this->getFactory()->defaultSettings();
    }

    public function formatters(): FormatterRegistry
    {
        return $this->getFactory()->createFormatterRegistry();
    }

    public function createCache(string $baseDir): LintCache
    {
        return $this->getFactory()->createLintCache($baseDir);
    }
}
