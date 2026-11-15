<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\Health\HealthChecker;
use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phar;
use Phel\Config\PhelConfig;
use Phel\Run\Domain\Config\ConfigDiagnostics;
use Phel\Run\Domain\Config\EffectiveConfigResult;
use Phel\Run\Domain\Doctor\InstallationReport;
use Phel\Run\Domain\Doctor\InstalledPackages;
use Phel\Run\Domain\Doctor\InstallMode;
use Phel\Run\Domain\Doctor\VendorPhelLibraries;
use Phel\Run\RunFacade;
use Phel\Shared\ByteSize;
use Phel\Shared\Performance\OpcacheAdvisor;
use Phel\Shared\Performance\OpcacheFileCache;
use Phel\Shared\PhelProjectDirectory;
use Phel\Shared\ScalarCoercion;
use Phel\Shared\VersionResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function count;
use function dirname;
use function extension_loaded;
use function file_get_contents;
use function get_included_files;
use function in_array;
use function ini_get;
use function is_file;
use function realpath;
use function sprintf;
use function str_contains;
use function str_replace;

/**
 * @method RunFacade getFacade()
 *
 * @internal
 */
#[ServiceMap(method: 'getFacade', className: RunFacade::class)]
final class DoctorCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('doctor')
            ->setDescription('Check system requirements for running the Phel CLI')
            ->setHelp(<<<'HELP'
Checks PHP version/extensions, how Phel is installed and what the project
loads from vendor/, module health, project configuration, and
cold-start performance (OPcache CLI caching), printing actionable fixes for
anything missing.

<info>Example:</info>
  <comment>phel doctor</comment>
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $effectiveConfig = $this->getFacade()->readEffectiveConfig();

        $systemOk = $this->checkSystemRequirements($output);
        $this->checkInstallation($output, $effectiveConfig);
        $modulesOk = $this->checkModuleHealth($output);
        $configOk = $this->checkConfiguration($output, $effectiveConfig);
        $this->checkPerformance($output);
        $this->checkStorage($output, $effectiveConfig->projectRoot);

        if ($systemOk && $modulesOk && $configOk) {
            $output->writeln('<info>Your system meets all requirements.</info>');

            return Command::SUCCESS;
        }

        $output->writeln('<error>Your system does not meet all requirements.</error>');

        return Command::FAILURE;
    }

    private function checkSystemRequirements(OutputInterface $output): bool
    {
        $output->writeln('Checking requirements:');

        // PHP version is enforced by composer.json, so by the time we run we
        // already satisfy the minimum. Only runtime-optional requirements are
        // checked here.
        $requirements = [
            ['label' => 'json extension', 'status' => extension_loaded('json')],
            ['label' => 'mbstring extension', 'status' => extension_loaded('mbstring')],
            ['label' => 'readline extension', 'status' => extension_loaded('readline')],
        ];

        $success = true;
        foreach ($requirements as $req) {
            $ok = $req['status'];
            $success = $success && $ok;
            $output->writeln(sprintf(' - %s: %s', $req['label'], $ok ? '<info>OK</info>' : '<error>FAIL</error>'));
        }

        return $success;
    }

    /**
     * Informational only: a second Phel or a shadowed PHP package warns, it
     * never fails the command.
     */
    private function checkInstallation(OutputInterface $output, EffectiveConfigResult $effective): void
    {
        $output->writeln('');
        $output->writeln('Checking installation:');

        $vendorName = ScalarCoercion::toString($effective->configuredValues[PhelConfig::VENDOR_DIR] ?? null, 'vendor');
        $vendorDir = $effective->projectRoot . '/' . $vendorName;
        $autoload = $vendorDir . '/autoload.php';
        $pharPath = Phar::running(false);

        // A broken vendor phel-config.php must not stop the remaining checks.
        $libraryScanError = null;
        try {
            $libraries = VendorPhelLibraries::group(
                $this->getFacade()->getAllPhelDirectories(),
                $vendorDir,
                realpath($vendorDir) ?: $vendorDir,
                $vendorName,
            );
        } catch (Throwable $throwable) {
            $libraries = [];
            $libraryScanError = $throwable->getMessage();
        }

        $report = new InstallationReport(
            mode: $this->installMode($pharPath),
            location: $pharPath !== '' ? $pharPath : dirname(__DIR__, 5),
            version: VersionResolver::current(),
            autoloaderPresent: is_file($autoload),
            autoloaderLoaded: in_array(realpath($autoload), get_included_files(), true),
            phelLibraries: $libraries,
            vendoredPackages: $this->installedPackages($vendorDir . '/composer/installed.json'),
            bundledPackages: $pharPath !== '' ? $this->installedPackages(Phar::running() . '/vendor/composer/installed.json') : [],
            vendorName: $vendorName,
            libraryScanError: $libraryScanError,
        );

        foreach ($report->lines() as $line) {
            $output->writeln($line);
        }
    }

    private function installMode(string $pharPath): InstallMode
    {
        if ($pharPath !== '') {
            return InstallMode::Phar;
        }

        return str_contains(str_replace('\\', '/', dirname(__DIR__, 5)), '/vendor/phel-lang/phel-lang')
            ? InstallMode::Composer
            : InstallMode::SourceCheckout;
    }

    /**
     * @return array<string, string>
     */
    private function installedPackages(string $installedJson): array
    {
        if (!is_file($installedJson)) {
            return [];
        }

        return InstalledPackages::fromJson((string) file_get_contents($installedJson));
    }

    private function checkModuleHealth(OutputInterface $output): bool
    {
        $output->writeln('');
        $output->writeln('Checking module health:');

        $report = new HealthChecker($this->getFacade()->getModuleHealthChecks())->checkAll();

        foreach ($report->getResults() as $moduleName => $status) {
            $output->writeln(sprintf(
                ' - %s: %s %s',
                $moduleName,
                $this->formatLevel($status),
                $status->message,
            ));
        }

        return !$report->hasUnhealthyModules();
    }

    /**
     * Reports problems with the effective project configuration. Errors fail
     * the check (and so the command); warnings are surfaced as tips.
     */
    private function checkConfiguration(OutputInterface $output, EffectiveConfigResult $effective): bool
    {
        $output->writeln('');
        $output->writeln('Checking configuration:');

        $issues = new ConfigDiagnostics()->analyze($effective->configuredValues, $effective->projectRoot);

        if ($issues === []) {
            $output->writeln(' - <info>OK</info> no configuration problems found');

            return true;
        }

        $ok = true;
        foreach ($issues as $issue) {
            if ($issue->isError()) {
                $ok = false;
                $output->writeln(sprintf(' - <error>FAIL</error> %s', $issue->message));
            } else {
                $output->writeln(sprintf(' - <comment>TIP</comment> %s', $issue->message));
            }
        }

        return $ok;
    }

    private function checkPerformance(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('Checking performance:');

        $advice = new OpcacheAdvisor()->advise(
            opcacheLoaded: extension_loaded('Zend OPcache'),
            enableCli: (bool) ini_get('opcache.enable_cli'),
            fileCacheConfigured: (string) ini_get('opcache.file_cache') !== '',
            iniTemplatePath: $this->bundledIniTemplatePath(),
        );

        if ($advice->optimal) {
            $output->writeln(sprintf(' - OPcache CLI caching: <info>OK</info> %s', $advice->messages[0]));

            return;
        }

        foreach ($advice->messages as $message) {
            $output->writeln(sprintf(' - OPcache CLI caching: <comment>TIP</comment> %s', $message));
        }
    }

    /**
     * Reports the OPcache file cache Phel writes under the project state
     * directory. It is invisible to every other command and is normally the
     * largest thing there, so `doctor` names its size and how much of it a
     * retired system id has made unreadable (#3268).
     */
    private function checkStorage(OutputInterface $output, string $projectRoot): void
    {
        $output->writeln('');
        $output->writeln('Checking storage:');

        $fileCache = new OpcacheFileCache(PhelProjectDirectory::opcachePath($projectRoot));
        if (!$fileCache->exists()) {
            $output->writeln(' - OPcache file cache: <info>OK</info> not created yet');

            return;
        }

        $output->writeln(sprintf(
            ' - OPcache file cache: %s in %s',
            ByteSize::format($fileCache->sizeInBytes()),
            $fileCache->path(),
        ));

        // __FILE__ is compiled by the process asking, so its own .bin names the
        // system id in force; an empty answer means the file cache is off here.
        $systemId = $fileCache->detectSystemId(__FILE__);
        $subtreeCount = count($fileCache->entries());

        if ($systemId === '') {
            $output->writeln(sprintf(' - Subtrees: %d (current system id unknown)', $subtreeCount));
        } else {
            $output->writeln(sprintf(
                ' - Unreadable subtrees: %d of %d (a PHP or ini change retires a system id)',
                count($fileCache->foreignEntries($systemId)),
                $subtreeCount,
            ));
        }

        $output->writeln(' - Reclaim it with <comment>phel cache:clear</comment>');
    }

    /**
     * Absolute path to the `phel.ini` template bundled at the package root,
     * or null when it cannot be usefully referenced — a trimmed distribution
     * (file absent) or a PHAR run, where the file resolves to a `phar://…`
     * path that PHP cannot load via `php -c`. PHAR users still get the
     * generic OPcache tips, just without a broken config pointer.
     */
    private function bundledIniTemplatePath(): ?string
    {
        if (Phar::running(false) !== '') {
            return null;
        }

        $path = dirname(__DIR__, 5) . '/phel.ini';

        return is_file($path) ? $path : null;
    }

    private function formatLevel(HealthStatus $status): string
    {
        return match (true) {
            $status->isHealthy() => '<info>OK</info>',
            $status->isDegraded() => '<comment>DEGRADED</comment>',
            default => '<error>FAIL</error>',
        };
    }
}
