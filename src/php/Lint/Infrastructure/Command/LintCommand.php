<?php

declare(strict_types=1);

namespace Phel\Lint\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Lint\Application\Cache\LintCache;
use Phel\Lint\Application\Config\RuleSettings;
use Phel\Lint\Application\Formatter\HumanFormatter;
use Phel\Lint\LintConfig;
use Phel\Lint\LintFacade;
use Phel\Lint\LintFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function getcwd;
use function is_dir;
use function is_file;
use function is_string;
use function rtrim;
use function sprintf;

/**
 * `./bin/phel lint <paths>` — read-only semantic linter. Exits with:
 *   0 — no errors
 *   1 — one or more errors (warnings/infos do not fail)
 *   2 — invocation error (bad flags, no readable files).
 */
#[ServiceMap(method: 'getFacade', className: LintFacade::class)]
#[ServiceMap(method: 'getFactory', className: LintFactory::class)]
#[ServiceMap(method: 'getConfig', className: LintConfig::class)]
final class LintCommand extends Command
{
    use ServiceResolverAwareTrait;

    public const int EXIT_INVOCATION_ERROR = 2;

    private const string COMMAND_NAME = 'lint';

    private const string ARG_PATHS = 'paths';

    private const string OPT_FORMAT = 'format';

    private const string OPT_CONFIG = 'config';

    private const string OPT_NO_CACHE = 'no-cache';

    public function __construct()
    {
        parent::__construct(self::COMMAND_NAME);
    }

    protected function configure(): void
    {
        $this->setDescription('Run the semantic linter on one or more Phel files or directories.')
            ->addArgument(
                self::ARG_PATHS,
                InputArgument::IS_ARRAY,
                'Files or directories to lint (defaults to the configured source dirs).',
                [],
            )
            ->addOption(
                self::OPT_FORMAT,
                null,
                InputOption::VALUE_REQUIRED,
                'Output format: human, json, github.',
                HumanFormatter::NAME,
            )
            ->addOption(
                self::OPT_CONFIG,
                null,
                InputOption::VALUE_REQUIRED,
                'Path to a .phel-lint.phel config (defaults to <cwd>/phel-lint.phel).',
            )
            ->addOption(
                self::OPT_NO_CACHE,
                null,
                InputOption::VALUE_NONE,
                'Disable the incremental lint cache for this run.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $paths */
        $paths = (array) $input->getArgument(self::ARG_PATHS);
        if ($paths === []) {
            $paths = $this->defaultPaths();
        }

        $paths = $this->filterExistingPaths($paths);
        if ($paths === []) {
            $output->writeln('<error>No readable .phel files or directories found to lint.</error>');

            return self::EXIT_INVOCATION_ERROR;
        }

        $format = (string) $input->getOption(self::OPT_FORMAT);
        $formatters = $this->getFacade()->formatters();
        if (!$formatters->has($format)) {
            $output->writeln(sprintf(
                '<error>Unknown format: %s. Known: %s.</error>',
                $format,
                implode(', ', $formatters->names()),
            ));

            return self::EXIT_INVOCATION_ERROR;
        }

        $settings = $this->loadSettings($input);
        $cache = $this->maybeCache($input);

        // Load phel core so analyzeSource() resolves core symbols like
        // `defn`, `let`, etc. Without this the linter's first diagnostic
        // on any well-formed file is "cannot resolve symbol defn".
        $this->getFactory()->getRunFacade()->loadPhelNamespaces();

        try {
            $result = $this->getFacade()->lint($paths, $settings, $cache);
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>Lint failed: %s</error>', $throwable->getMessage()));

            return self::EXIT_INVOCATION_ERROR;
        }

        $output->write($formatters->get($format)->format($result));
        $output->writeln('');

        return $result->hasErrors() ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function defaultPaths(): array
    {
        $cmd = $this->getFactory()->getCommandFacade();

        return $cmd->getProjectSourceDirectories();
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function filterExistingPaths(array $paths): array
    {
        $filtered = [];
        foreach ($paths as $path) {
            if (is_file($path) || is_dir($path)) {
                $filtered[] = $path;
            }
        }

        return $filtered;
    }

    private function loadSettings(InputInterface $input): RuleSettings
    {
        $defaults = $this->getFacade()->defaultSettings();
        $configPath = $input->getOption(self::OPT_CONFIG);
        if (!is_string($configPath) || $configPath === '') {
            $configPath = $this->defaultConfigPath();
        }

        return $this->getFacade()->loadSettings($configPath, $defaults);
    }

    private function defaultConfigPath(): string
    {
        $cwd = getcwd();
        if ($cwd === false) {
            return LintConfig::defaultConfigFilename();
        }

        return rtrim($cwd, '/') . '/' . LintConfig::defaultConfigFilename();
    }

    private function maybeCache(InputInterface $input): ?LintCache
    {
        if ($input->getOption(self::OPT_NO_CACHE)) {
            return null;
        }

        $cwd = getcwd();
        if ($cwd === false) {
            return null;
        }

        $cacheDir = rtrim($cwd, '/') . '/' . LintConfig::defaultCacheDir();

        return $this->getFacade()->createCache($cacheDir);
    }
}
