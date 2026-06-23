<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\Health\HealthChecker;
use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phar;
use Phel\Run\RunFacade;
use Phel\Shared\Performance\OpcacheAdvisor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function dirname;
use function extension_loaded;
use function ini_get;
use function sprintf;

/**
 * @method RunFacade getFacade()
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
Checks PHP version/extensions, module health, and cold-start performance
(OPcache CLI caching), printing actionable fixes for anything missing.

<info>Example:</info>
  <comment>phel doctor</comment>
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $systemOk = $this->checkSystemRequirements($output);
        $modulesOk = $this->checkModuleHealth($output);
        $this->checkPerformance($output);

        if ($systemOk && $modulesOk) {
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
