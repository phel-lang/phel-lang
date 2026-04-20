<?php

declare(strict_types=1);

namespace Phel\Lint;

use Gacela\Framework\AbstractFactory;
use Phel\Api\ApiFacade;
use Phel\Command\CommandFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Lint\Application\Cache\LintCache;
use Phel\Lint\Application\Config\ConfigLoader;
use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Application\Config\RuleSettings;
use Phel\Lint\Application\FileCollector;
use Phel\Lint\Application\Formatter\FormatterRegistry;
use Phel\Lint\Application\Formatter\GithubFormatter;
use Phel\Lint\Application\Formatter\HumanFormatter;
use Phel\Lint\Application\Formatter\JsonFormatter;
use Phel\Lint\Application\LintRunner;
use Phel\Lint\Application\Rule\ArityMismatchRule;
use Phel\Lint\Application\Rule\DiscouragedVarRule;
use Phel\Lint\Application\Rule\DuplicateKeyRule;
use Phel\Lint\Application\Rule\InvalidDestructuringRule;
use Phel\Lint\Application\Rule\RedundantDoRule;
use Phel\Lint\Application\Rule\ShadowedBindingRule;
use Phel\Lint\Application\Rule\UnresolvedSymbolRule;
use Phel\Lint\Application\Rule\UnusedBindingRule;
use Phel\Lint\Application\Rule\UnusedImportRule;
use Phel\Lint\Application\Rule\UnusedRequireRule;
use Phel\Lint\Application\RulePipeline;
use Phel\Lint\Application\SourceReader;
use Phel\Lint\Domain\LintRuleInterface;
use Phel\Run\RunFacade;

use function implode;
use function md5;
use function sort;

/**
 * @extends AbstractFactory<LintConfig>
 */
final class LintFactory extends AbstractFactory
{
    public function createLintRunner(?LintCache $cache = null): LintRunner
    {
        return new LintRunner(
            $this->getApiFacade(),
            $this->createFileCollector(),
            $this->createSourceReader(),
            $this->createRulePipeline(),
            $cache,
        );
    }

    public function createRulePipeline(): RulePipeline
    {
        return new RulePipeline($this->createRules());
    }

    /**
     * @return list<LintRuleInterface>
     */
    public function createRules(): array
    {
        return [
            new UnresolvedSymbolRule(),
            new ArityMismatchRule(),
            new UnusedBindingRule(),
            new UnusedRequireRule(),
            new UnusedImportRule(),
            new ShadowedBindingRule(),
            new RedundantDoRule(),
            new DuplicateKeyRule($this->getCompilerFacade()),
            new InvalidDestructuringRule(),
            new DiscouragedVarRule(),
        ];
    }

    public function defaultSettings(): RuleSettings
    {
        return $this->getConfig()->defaultSettings();
    }

    public function createFormatterRegistry(): FormatterRegistry
    {
        $registry = new FormatterRegistry();
        $registry->register(new HumanFormatter());
        $registry->register(new JsonFormatter());
        $registry->register(new GithubFormatter());

        return $registry;
    }

    public function createConfigLoader(): ConfigLoader
    {
        return new ConfigLoader($this->getCompilerFacade());
    }

    public function createSourceReader(): SourceReader
    {
        return new SourceReader($this->getCompilerFacade());
    }

    public function createFileCollector(): FileCollector
    {
        return new FileCollector();
    }

    public function createLintCache(string $cacheDir): LintCache
    {
        return new LintCache($cacheDir, $this->ruleFingerprint());
    }

    public function getApiFacade(): ApiFacade
    {
        return $this->getProvidedDependency(LintProvider::FACADE_API);
    }

    public function getCompilerFacade(): CompilerFacade
    {
        return $this->getProvidedDependency(LintProvider::FACADE_COMPILER);
    }

    public function getCommandFacade(): CommandFacade
    {
        return $this->getProvidedDependency(LintProvider::FACADE_COMMAND);
    }

    public function getRunFacade(): RunFacade
    {
        return $this->getProvidedDependency(LintProvider::FACADE_RUN);
    }

    /**
     * Deterministic fingerprint covering the rule set. Drives cache
     * invalidation when rules are added, removed, or reordered.
     */
    private function ruleFingerprint(): string
    {
        $codes = RuleRegistry::allCodes();
        sort($codes);

        return md5(implode('|', $codes));
    }
}
