<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\Gacela;
use Gacela\Framework\Health\HealthChecker;
use Gacela\Framework\Health\HealthStatus;
use Phel\Build\BuildFacade;
use Phel\Filesystem\FilesystemFacade;
use Phel\Run\Application\Agent\AgentInstallStatusInspector;
use Phel\Run\Application\Agent\AgentVersionStamper;
use Phel\Run\Domain\Agent\AgentPlatformRegistry;
use Phel\Run\Domain\Agent\AgentPlatformStatus;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function dirname;
use function extension_loaded;
use function getcwd;
use function is_dir;
use function sprintf;

final class DoctorCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('doctor')
            ->setDescription('Check system requirements for running the Phel CLI');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $systemOk = $this->checkSystemRequirements($output);
        $modulesOk = $this->checkModuleHealth($output);
        $this->reportAgentInstallStatus($output);

        if ($systemOk && $modulesOk) {
            $output->writeln('<info>Your system meets all requirements.</info>');

            return Command::SUCCESS;
        }

        $output->writeln('<error>Your system does not meet all requirements.</error>');

        return Command::FAILURE;
    }

    private function reportAgentInstallStatus(OutputInterface $output): void
    {
        $sourceRoot = $this->agentsRoot();
        if ($sourceRoot === null) {
            return;
        }

        $stamper = new AgentVersionStamper($sourceRoot);
        $inspector = new AgentInstallStatusInspector(new AgentPlatformRegistry(), $stamper);
        $statuses = $inspector->inspect((string) getcwd());

        $output->writeln('');
        $output->writeln(sprintf(
            'AI agent skills (phel-agents v%s):',
            $stamper->currentVersion() ?? 'unknown',
        ));

        $installedCount = 0;
        foreach ($statuses as $status) {
            if ($status->state === AgentPlatformStatus::NOT_INSTALLED) {
                continue;
            }

            ++$installedCount;
            $output->writeln(sprintf(
                ' - %-8s %s',
                $status->platform->key,
                $this->formatAgentState($status),
            ));
        }

        if ($installedCount === 0) {
            $output->writeln(' - none installed; run <comment>phel agent-install --auto</comment>');
        }
    }

    private function formatAgentState(AgentPlatformStatus $status): string
    {
        return match ($status->state) {
            AgentPlatformStatus::CURRENT => sprintf('<info>OK</info> v%s', $status->installedVersion ?? '?'),
            AgentPlatformStatus::STALE => sprintf('<comment>STALE</comment> v%s (current v%s) — refresh with `agent-install %s --force`', $status->installedVersion ?? '?', $status->currentVersion, $status->platform->key),
            AgentPlatformStatus::UNSTAMPED => sprintf('<comment>UNSTAMPED</comment> — refresh with `agent-install %s --force`', $status->platform->key),
            default => $status->state,
        };
    }

    private function agentsRoot(): ?string
    {
        foreach ([5, 4, 6] as $levels) {
            $candidate = dirname(__DIR__, $levels) . '/resources/agents';
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return null;
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

        $report = new HealthChecker([
            Gacela::getRequired(FilesystemFacade::class)->getHealthCheck(),
            Gacela::getRequired(BuildFacade::class)->getHealthCheck(),
        ])->checkAll();

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

    private function formatLevel(HealthStatus $status): string
    {
        return match (true) {
            $status->isHealthy() => '<info>OK</info>',
            $status->isDegraded() => '<comment>DEGRADED</comment>',
            default => '<error>FAIL</error>',
        };
    }
}
