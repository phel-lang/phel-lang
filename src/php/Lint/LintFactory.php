<?php

declare(strict_types=1);

namespace Phel\Lint;

use Composer\InstalledVersions;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Phel\Lint\Application\Cache\LintCache;
use Phel\Lint\Application\Cache\LintCacheFingerprint;
use Phel\Lint\Application\Config\ConfigLoader;
use Phel\Lint\Application\Config\RuleSettings;
use Phel\Lint\Application\FileCollector;
use Phel\Lint\Application\Formatter\FormatterRegistry;
use Phel\Lint\Application\Formatter\GithubFormatter;
use Phel\Lint\Application\Formatter\HumanFormatter;
use Phel\Lint\Application\Formatter\JsonFormatter;
use Phel\Lint\Application\LintRunner;
use Phel\Lint\Application\Rule\ArityMismatchRule;
use Phel\Lint\Application\Rule\CommentStyleRule;
use Phel\Lint\Application\Rule\DiscouragedVarRule;
use Phel\Lint\Application\Rule\DuplicateDefRule;
use Phel\Lint\Application\Rule\DuplicateKeyRule;
use Phel\Lint\Application\Rule\InvalidDestructuringRule;
use Phel\Lint\Application\Rule\RedundantDoRule;
use Phel\Lint\Application\Rule\ShadowedBindingRule;
use Phel\Lint\Application\Rule\ShadowedCoreFnRule;
use Phel\Lint\Application\Rule\UnresolvedNamespaceRule;
use Phel\Lint\Application\Rule\UnresolvedSymbolRule;
use Phel\Lint\Application\Rule\UnusedBindingRule;
use Phel\Lint\Application\Rule\UnusedImportRule;
use Phel\Lint\Application\Rule\UnusedRequireRule;
use Phel\Lint\Application\RulePipeline;
use Phel\Lint\Application\SourceReader;
use Phel\Lint\Domain\LintRuleCatalog;
use Phel\Lint\Domain\LintRuleInterface;
use Phel\Lint\Infrastructure\ProjectKnownNamespaces;
use Phel\Lint\Infrastructure\RegistryCoreFunctionNames;
use Phel\Shared\Facade\ApiFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Facade\RunFacadeInterface;
use Phel\Shared\Lint\LintRuleExplainerInterface;
use Phel\Shared\LintRuleCodes;
use Phel\Shared\VersionFinder;

/**
 * @extends AbstractFactory<LintConfig>
 *
 * @internal
 */
#[ServiceMap(method: 'getConfig', className: LintConfig::class)]
final class LintFactory extends AbstractFactory
{
    private const string PHEL_PACKAGE = 'phel-lang/phel-lang';

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
            new UnresolvedNamespaceRule(new ProjectKnownNamespaces($this->getRunFacade()), $this->getCompilerFacade()),
            new ArityMismatchRule(),
            new UnusedBindingRule(),
            new UnusedRequireRule(),
            new UnusedImportRule(),
            new ShadowedBindingRule(),
            new ShadowedCoreFnRule(new RegistryCoreFunctionNames()),
            new RedundantDoRule(),
            new DuplicateKeyRule($this->getCompilerFacade()),
            new DuplicateDefRule(),
            new InvalidDestructuringRule(),
            new DiscouragedVarRule(),
            new CommentStyleRule($this->getCompilerFacade()),
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

    public function createRuleExplainer(): LintRuleExplainerInterface
    {
        return new LintRuleCatalog();
    }

    public function createLintCache(string $cacheDir, RuleSettings $settings): LintCache
    {
        return new LintCache($cacheDir, $this->ruleFingerprint($settings));
    }

    public function getApiFacade(): ApiFacadeInterface
    {
        return $this->getProvidedDependency(ApiFacadeInterface::class);
    }

    public function getCompilerFacade(): CompilerFacadeInterface
    {
        return $this->getProvidedDependency(CompilerFacadeInterface::class);
    }

    public function getCommandFacade(): CommandFacadeInterface
    {
        return $this->getProvidedDependency(CommandFacadeInterface::class);
    }

    public function getRunFacade(): RunFacadeInterface
    {
        return $this->getProvidedDependency(RunFacadeInterface::class);
    }

    /**
     * Drives cache invalidation when Phel is upgraded, when rules are
     * added or removed, or when phel-lint.phel is edited.
     */
    private function ruleFingerprint(RuleSettings $settings): string
    {
        return LintCacheFingerprint::of($this->installedPhelVersion(), LintRuleCodes::allCodes(), $settings->fingerprint());
    }

    /**
     * The release tag plus the installed commit, so moving between
     * development commits of the same release counts as an upgrade too.
     * Read from Composer's metadata rather than `git`, which costs a process.
     */
    private function installedPhelVersion(): string
    {
        $reference = InstalledVersions::isInstalled(self::PHEL_PACKAGE)
            ? InstalledVersions::getReference(self::PHEL_PACKAGE)
            : null;

        return VersionFinder::LATEST_VERSION . '@' . ($reference ?? '');
    }
}
