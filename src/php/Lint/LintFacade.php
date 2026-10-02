<?php

declare(strict_types=1);

namespace Phel\Lint;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Phel\Lint\Application\Cache\LintCache;
use Phel\Lint\Application\Config\RuleSettings;
use Phel\Lint\Application\Formatter\FormatterRegistry;
use Phel\Lint\Domain\Exception\LintConfigException;
use Phel\Lint\Domain\Exception\LintSourceException;
use Phel\Lint\Domain\LintRuleCatalog;
use Phel\Lint\Domain\LintRuleExplanation;
use Phel\Lint\Transfer\LintResult;
use Phel\Shared\Facade\LintFacadeInterface;

use function array_map;

/**
 * @extends AbstractFacade<LintFactory>
 */
#[ServiceMap(method: 'getFactory', className: LintFactory::class)]
final class LintFacade extends AbstractFacade implements LintFacadeInterface
{
    /**
     * @param list<string> $paths
     *
     * @throws LintSourceException when a collected `.phel` file cannot be read
     */
    public function lint(array $paths, RuleSettings $settings, ?LintCache $cache = null): LintResult
    {
        return $this->getFactory()
            ->createLintRunner($cache)
            ->run($paths, $settings);
    }

    /**
     * @throws LintConfigException when $configPath exists but is unreadable or malformed
     */
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

    public function createCache(string $baseDir, RuleSettings $settings): LintCache
    {
        return $this->getFactory()->createLintCache($baseDir, $settings);
    }

    public function explainRule(string $code): ?array
    {
        return LintRuleCatalog::find($code)?->toArray();
    }

    public function ruleExplanations(): array
    {
        return array_map(
            static fn(LintRuleExplanation $explanation): array => $explanation->toArray(),
            LintRuleCatalog::all(),
        );
    }
}
